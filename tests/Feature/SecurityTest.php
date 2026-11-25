<?php

namespace Tests\Feature;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Files\FileValidator;
use App\Models\ActivityLog;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
    }

    private function fake(string $content, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'smmx');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_berkas_ditolak_bila_isi_tidak_sesuai_ekstensi(): void
    {
        $v = app(FileValidator::class);
        $this->expectException(ValidationException::class);
        $v->validate($this->fake('<?php echo "x";', 'jahat.pdf'), ['pdf']);
    }

    public function test_ekstensi_tidak_diizinkan_ditolak(): void
    {
        $this->expectException(ValidationException::class);
        app(FileValidator::class)->validate($this->fake('MZ...', 'program.exe'), ['pdf', 'docx']);
    }

    public function test_docx_bermakro_ditolak(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'smmm').'.docx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('word/document.xml', '<document/>');
        $zip->addFromString('word/vbaProject.bin', 'macro');
        $zip->close();
        $this->expectException(ValidationException::class);
        app(FileValidator::class)->validate(new UploadedFile($path, 'makro.docx', null, null, true), ['docx']);
    }

    public function test_batas_ukuran_bisa_diatur(): void
    {
        Settings::set('upload.max_mb', 1);
        $this->expectException(ValidationException::class);
        app(FileValidator::class)->validate($this->fake('%PDF-1.4'.str_repeat('x', 1024 * 1024 + 10), 'besar.pdf'), ['pdf']);
    }

    public function test_pdf_valid_diterima(): void
    {
        [$ext, $mime] = app(FileValidator::class)->validate($this->pdf(), ['pdf']);
        $this->assertSame('pdf', $ext);
        $this->assertSame('application/pdf', $mime);
    }

    public function test_log_aktivitas_tidak_bisa_diubah_atau_dihapus_lewat_aplikasi(): void
    {
        $log = app(ActivityLogger::class)->log('sistem', 'Uji');
        try {
            $log->update(['description' => 'diubah']);
            $this->fail('Update harus ditolak');
        } catch (LogicException) {
        }
        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_perubahan_langsung_di_database_terdeteksi_rantai_hash(): void
    {
        $logger = app(ActivityLogger::class);
        $logger->log('sistem', 'Satu', null, ['a' => 1]);
        $two = $logger->log('sistem', 'Dua');
        $logger->log('sistem', 'Tiga');
        $this->assertSame([], $logger->verify());

        DB::table('activity_logs')->where('id', $two->id)->update(['description' => 'Dua (dipalsukan)']);
        $this->assertSame([$two->id], $logger->verify());
        $this->artisan('smm:verify-log')->assertFailed();
    }

    public function test_halaman_admin_ditolak_untuk_non_admin(): void
    {
        $staf = $this->makeUser('staf', 'CS');
        foreach (['admin.users.index', 'admin.settings.index', 'admin.approval-rules.index', 'admin.backup.index', 'admin.drive.index', 'activity.index'] as $r) {
            $this->actingAs($staf)->get(route($r))->assertForbidden();
        }
        $this->actingAs($this->makeUser('gm'))->get(route('admin.users.index'))->assertOk();
    }

    public function test_pengguna_dinonaktifkan_bukan_dihapus(): void
    {
        $gm = $this->makeUser('gm');
        $staf = $this->makeUser('staf', 'CS');
        $this->actingAs($gm)->post(route('admin.users.toggle', $staf))->assertRedirect();
        $this->assertFalse($staf->fresh()->is_active);
        $this->assertDatabaseHas('users', ['id' => $staf->id]);
        $this->assertFalse(\Route::has('admin.users.destroy'));
    }

    public function test_csrf_aktif_untuk_form(): void
    {
        $this->withMiddleware();
        $this->actingAs($this->makeUser('gm'));
        // Tanpa token CSRF → 419
        $this->app['env'] = 'production';
        $res = $this->call('POST', route('notifications.read-all'));
        $this->assertSame(419, $res->getStatusCode());
    }

    public function test_installer_terkunci_setelah_ada_pengguna(): void
    {
        $this->makeUser('gm');
        $this->get(route('install'))->assertNotFound();
    }

    public function test_batas_percobaan_login(): void
    {
        $u = $this->makeUser('staf', 'CS');
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['email' => $u->email, 'password' => 'salah']);
        }
        $this->post(route('login'), ['email' => $u->email, 'password' => 'password'])->assertStatus(429);
        $this->assertGuest();
        $this->assertGreaterThanOrEqual(5, ActivityLog::where('action', 'login_gagal')->count());
    }
}
