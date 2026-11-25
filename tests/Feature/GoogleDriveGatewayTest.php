<?php

namespace Tests\Feature;

use App\Domain\Drive\DriveException;
use App\Domain\Drive\GoogleDriveGateway;
use App\Domain\Drive\GoogleOAuth;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Menguji pemanggilan REST Google Drive dengan respons tiruan (tanpa internet).
 * Uji ke Google sungguhan: Admin → Google Drive → Uji koneksi, lalu Admin → Backup.
 */
class GoogleDriveGatewayTest extends TestCase
{
    private string $tokenPath;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->tokenPath = storage_path('framework/testing/google-token-'.getmypid().'.json');
        @mkdir(dirname($this->tokenPath), 0700, true);
        file_put_contents($this->tokenPath, Crypt::encryptString(json_encode(['refresh_token' => 'rt-123', 'email' => 'info@contoh.test'])));
    }

    protected function tearDown(): void
    {
        @unlink($this->tokenPath);
        parent::tearDown();
    }

    private function gateway(): GoogleDriveGateway
    {
        return new GoogleDriveGateway(new GoogleOAuth('client-id', 'client-secret', $this->tokenPath));
    }

    public function test_token_diperbarui_dan_folder_dicari_lalu_dibuat(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at-1', 'expires_in' => 3600]),
            'www.googleapis.com/drive/v3/files?q=*' => Http::sequence()
                ->push(['files' => [['id' => 'root-smm', 'name' => 'SMM']]])   // "SMM" sudah ada
                ->push(['files' => []]),                                      // "Dokumen Terkendali" belum ada
            'www.googleapis.com/drive/v3/files?fields=id' => Http::response(['id' => 'folder-baru']),
        ]);

        $id = $this->gateway()->ensureFolderPath('SMM', 'Dokumen Terkendali');
        $this->assertSame('folder-baru', $id);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'oauth2.googleapis.com/token') && $r['refresh_token'] === 'rt-123');
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/files?fields=id')
            && $r['name'] === 'Dokumen Terkendali' && $r['parents'] === ['root-smm'] && $r['mimeType'] === 'application/vnd.google-apps.folder'
            && $r->hasHeader('Authorization', 'Bearer at-1'));
    }

    public function test_unggah_resumable_mengembalikan_id_dan_md5(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'gd');
        file_put_contents($file, 'isi berkas uji');
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at-1', 'expires_in' => 3600]),
            'www.googleapis.com/upload/drive/v3/files*' => Http::response('', 200, ['Location' => 'https://www.googleapis.com/upload/session/abc']),
            'www.googleapis.com/upload/session/abc' => Http::response(['id' => 'file-1', 'md5Checksum' => md5('isi berkas uji'), 'size' => '14']),
        ]);

        $res = $this->gateway()->upload($file, 'folder-1', 'Rev 00 - Uji.pdf', 'application/pdf');
        $this->assertSame(['id' => 'file-1', 'md5' => md5('isi berkas uji'), 'size' => 14], $res);
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r->body() === 'isi berkas uji' && $r->hasHeader('Content-Range', 'bytes 0-13/14'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'uploadType=resumable') && $r['parents'] === ['folder-1'] && $r['name'] === 'Rev 00 - Uji.pdf');
    }

    public function test_daftar_berkas_mengikuti_halaman_berikutnya_dan_hapus(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at-1', 'expires_in' => 3600]),
            'www.googleapis.com/drive/v3/files?*' => Http::sequence()
                ->push(['files' => [['id' => 'a', 'name' => 'smm-harian-1.zip', 'size' => '10']], 'nextPageToken' => 'p2'])
                ->push(['files' => [['id' => 'b', 'name' => 'smm-harian-2.zip', 'size' => '20']]]),
            'www.googleapis.com/drive/v3/files/a' => Http::response('', 204),
        ]);
        $g = $this->gateway();
        $this->assertSame(['a', 'b'], array_column($g->listFiles('folder'), 'id'));
        $g->delete('a');
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/files/a'));
    }

    public function test_metadata_berkas_hilang_mengembalikan_null(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at-1', 'expires_in' => 3600]),
            'www.googleapis.com/drive/v3/files/hilang*' => Http::response(['error' => 'notFound'], 404),
            'www.googleapis.com/drive/v3/files/sampah*' => Http::response(['id' => 'sampah', 'trashed' => true]),
        ]);
        $this->assertNull($this->gateway()->metadata('hilang'));
        $this->assertNull($this->gateway()->metadata('sampah'));
    }

    public function test_token_dicabut_memberi_pesan_jelas(): void
    {
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);
        $this->expectException(DriveException::class);
        $this->expectExceptionMessage('Hubungkan ulang');
        $this->gateway()->health();
    }

    public function test_unduh_ke_berkas(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at-1', 'expires_in' => 3600]),
            'www.googleapis.com/drive/v3/files/f1*' => Http::response('%PDF-isi'),
        ]);
        $dest = sys_get_temp_dir().'/gd-'.uniqid().'.pdf';
        $this->gateway()->downloadTo('f1', $dest);
        $this->assertSame('%PDF-isi', file_get_contents($dest));
        @unlink($dest);
    }
}
