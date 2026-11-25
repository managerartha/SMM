<?php

namespace Tests\Feature;

use App\Domain\Backup\BackupService;
use App\Domain\Backup\RestoreService;
use App\Domain\Documents\DocumentWorkflow;
use App\Domain\Drive\DriveGateway;
use App\Models\BackupRun;
use App\Models\Division;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\StoredFile;
use App\Models\User;
use App\Notifications\SmmNotification;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use ZipArchive;

/**
 * Backup → Drive (penyimpanan lokal pengganti Drive dengan struktur folder yang sama) → restore.
 * Uji ke Google Drive sungguhan dijalankan manual setelah OAuth terhubung (docs/RESTORE.md).
 */
class BackupRestoreTest extends TestCase
{
    use DatabaseMigrations;

    private User $gm;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seedBase();
        $this->gm = $this->makeUser('gm');
    }

    private function createDoc(string $title): Document
    {
        return app(DocumentWorkflow::class)->create($this->gm, [
            'document_type_id' => DocumentType::where('code', 'SOP')->value('id'),
            'division_id' => Division::where('code', 'OPS')->value('id'),
            'title' => $title, 'access_scope' => 'umum',
        ], $this->pdf($title));
    }

    private function backupZipPath(BackupRun $run, string $folder): string
    {
        return $this->driveRoot.'/'.config('smm.drive.backup_root').'/'.$folder.'/'.$run->file_name;
    }

    public function test_backup_terenkripsi_dan_restore_mengembalikan_data(): void
    {
        $doc = $this->createDoc('SOP Sebelum Backup');
        $run = app(BackupService::class)->run('harian');
        $this->assertSame('berhasil', $run->status, (string) $run->message);
        $zipPath = $this->backupZipPath($run, 'Harian');
        $this->assertFileExists($zipPath);

        // Isi ZIP terenkripsi: tanpa kata sandi tidak bisa dibaca.
        $zip = new ZipArchive;
        $zip->open($zipPath);
        $this->assertFalse($zip->getFromName('database.sql'), 'database.sql tidak boleh terbaca tanpa kata sandi');
        $zip->setPassword(config('smm.backup.password'));
        $this->assertStringContainsString('SOP Sebelum Backup', $zip->getFromName('database.sql'));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $zip->close();
        $this->assertCount(1, $manifest['files']);
        $this->assertSame('SMM/Dokumen Terkendali/SOP/ASI-SOP-OPS-01/Rev 00 - SOP Sebelum Backup.pdf', $manifest['files'][0]['drive_path']);

        // Data berubah setelah backup…
        $this->createDoc('SOP Setelah Backup');
        $doc->update(['title' => 'Judul diubah']);
        $this->assertSame(2, Document::count());

        // …restore dari Drive mengembalikan kondisi saat backup.
        $restore = app(RestoreService::class);
        $list = $restore->available();
        $this->assertSame($run->file_name, $list[0]['name']);
        $local = $restore->downloadFromDrive($list[0]['id']);
        $result = $restore->restoreFromZip($local, config('smm.backup.password'));

        $this->assertGreaterThan(15, $result['tables']);
        $this->assertSame(1, Document::count());
        $this->assertSame('SOP Sebelum Backup', Document::first()->title);
        $this->assertTrue(User::where('email', $this->gm->email)->exists());

        // Berkas tetap bisa diunduh setelah restore (berkas ada di Drive, dirujuk lewat ID).
        $file = StoredFile::first();
        $this->assertNotNull(app(DriveGateway::class)->metadata($file->drive_file_id));
        $rev = Document::first()->revisions()->first();
        $this->actingAs(User::where('email', $this->gm->email)->first())
            ->get(route('documents.download', [Document::first(), $rev, 'pdf']))->assertOk();
    }

    public function test_restore_ditolak_dengan_kata_sandi_salah(): void
    {
        $run = app(BackupService::class)->run('harian');
        $this->expectException(\RuntimeException::class);
        app(RestoreService::class)->restoreFromZip($this->backupZipPath($run, 'Harian'), 'kata-sandi-salah');
    }

    public function test_berkas_yang_belum_pindah_ke_drive_ikut_dalam_zip(): void
    {
        config(['queue.default' => 'database']); // berkas tertahan di server
        $this->createDoc('Tertunda');
        $this->assertSame(StoredFile::PENDING, StoredFile::first()->state);
        $run = app(BackupService::class)->run('harian');
        $zip = new ZipArchive;
        $zip->open($this->backupZipPath($run, 'Harian'));
        $zip->setPassword(config('smm.backup.password'));
        $this->assertNotFalse($zip->getFromName('files/'.basename(StoredFile::first()->local_path)));
        $zip->close();
    }

    public function test_retensi_menghapus_backup_lama(): void
    {
        Settings::set('backup.daily_retention', 7);
        for ($i = 0; $i < 9; $i++) {
            $this->travelTo(now()->addDay());
            app(BackupService::class)->run('harian');
        }
        $files = File::files($this->driveRoot.'/'.config('smm.drive.backup_root').'/Harian');
        $this->assertCount(7, $files);
    }

    public function test_backup_gagal_mengirim_email_ke_gm(): void
    {
        config(['smm.backup.password' => '']);
        $run = app(BackupService::class)->run('harian');
        $this->assertSame('gagal', $run->status);
        Notification::assertSentTo($this->gm, SmmNotification::class, fn ($n) => str_contains($n->subject, 'Backup'));
        $this->actingAs($this->gm)->get(route('admin.backup.index'))->assertOk()->assertSee('Gagal');
    }

    public function test_backup_bulanan_memeriksa_berkas_di_drive(): void
    {
        $this->createDoc('Cek berkas');
        $f = StoredFile::first();
        app(DriveGateway::class)->delete($f->drive_file_id); // simulasi berkas terhapus manual dari Drive
        $run = app(BackupService::class)->run('bulanan');
        $this->assertSame('berhasil', $run->status);
        $this->assertSame(1, $run->files_missing);
        Notification::assertSentTo($this->gm, SmmNotification::class);
    }

    public function test_perintah_artisan_backup_dan_restore(): void
    {
        $this->createDoc('Via artisan');
        $this->artisan('smm:backup bulanan')->assertSuccessful();
        $this->artisan('smm:restore --latest --force')->assertSuccessful();
        $this->assertSame('Via artisan', Document::first()->title);
    }
}
