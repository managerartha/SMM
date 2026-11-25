<?php

namespace App\Domain\Backup;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Drive\DriveFolders;
use App\Domain\Drive\DriveGateway;
use App\Domain\Files\FileStore;
use App\Domain\Notifications\Notifier;
use App\Models\BackupRun;
use App\Models\StoredFile;
use App\Models\User;
use App\Notifications\SmmNotification;
use App\Support\Settings;
use Druidfi\Mysqldump\Mysqldump;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Backup ke Google Drive:
 * - ZIP terenkripsi AES-256 berisi dump database (database.sql), manifest.json (daftar berkas + ID Drive + checksum),
 *   dan berkas yang belum sempat pindah ke Drive (files/).
 * - Harian → "Backup SMM/Harian" (retensi 30), Bulanan → "Backup SMM/Bulanan" (retensi 12).
 * - Berkas dokumen sendiri sudah tersimpan permanen di Drive (tidak pernah ditimpa/dihapus), jadi tidak disalin ulang.
 * - Bulanan juga memeriksa setiap berkas masih ada di Drive.
 */
class BackupService
{
    public const FOLDERS = ['harian' => 'Harian', 'bulanan' => 'Bulanan', 'manual' => 'Manual'];

    public function __construct(
        private DriveGateway $drive,
        private DriveFolders $folders,
        private FileStore $files,
        private Notifier $notifier,
        private ActivityLogger $log,
    ) {}

    public function run(string $type = 'harian', ?User $user = null): BackupRun
    {
        $type = array_key_exists($type, self::FOLDERS) ? $type : 'harian';
        $run = BackupRun::create(['type' => $type, 'status' => 'berjalan', 'started_at' => now(), 'triggered_by' => $user?->id]);
        $work = storage_path('app/private/tmp/backup-'.Str::random(12));
        File::ensureDirectoryExists($work, 0700);

        try {
            $password = (string) config('smm.backup.password');
            if (strlen($password) < 12) {
                throw new RuntimeException('SMM_BACKUP_PASSWORD belum diisi atau kurang dari 12 karakter.');
            }

            $sql = $work.'/database.sql';
            $this->dumpDatabase($sql);

            $manifest = $this->manifest($type);
            $missing = $type === 'bulanan' ? $this->verifyDriveFiles($manifest) : null;
            file_put_contents($work.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $name = sprintf('smm-%s-%s.zip', $type, now()->format('Y-m-d-His'));
            $zipPath = $work.'/'.$name;
            $this->zip($zipPath, $password, $sql, $work.'/manifest.json', $manifest);

            $folderId = $this->folders->idFor(config('smm.drive.backup_root'), self::FOLDERS[$type]);
            $uploaded = $this->drive->upload($zipPath, $folderId, $name, 'application/zip');
            if ($uploaded['md5'] && $uploaded['md5'] !== md5_file($zipPath)) {
                throw new RuntimeException('Checksum backup di Drive tidak cocok.');
            }

            $pruned = $type === 'manual' ? 0 : $this->prune($folderId, (int) Settings::get('backup.'.($type === 'bulanan' ? 'monthly' : 'daily').'_retention', $type === 'bulanan' ? 12 : 30));

            $run->update([
                'status' => 'berhasil', 'finished_at' => now(), 'file_name' => $name, 'drive_file_id' => $uploaded['id'],
                'size' => filesize($zipPath), 'files_in_manifest' => count($manifest['files']), 'files_missing' => $missing,
                'pruned' => $pruned,
                'message' => $missing ? "PERHATIAN: {$missing} berkas tidak ditemukan di Drive." : null,
            ]);
            $this->log->log('backup', "Backup {$type} berhasil: {$name}", $run, ['size' => $run->size], $user);

            if ($missing) {
                $this->notifyFailure($run, "{$missing} berkas yang tercatat di database tidak ditemukan di Google Drive. Periksa folder SMM di Drive (mungkin terhapus/terpindah).");
            }
        } catch (Throwable $e) {
            $run->update(['status' => 'gagal', 'finished_at' => now(), 'message' => mb_substr($e->getMessage(), 0, 2000)]);
            $this->log->log('backup', "Backup {$type} GAGAL: ".mb_substr($e->getMessage(), 0, 300), $run, [], $user);
            $this->notifyFailure($run, $e->getMessage());
            report($e);
        } finally {
            File::deleteDirectory($work);
        }

        return $run->fresh();
    }

    public function dumpDatabase(string $path): void
    {
        $c = config('database.connections.'.config('database.default'));
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['database']);
        $dump = new Mysqldump($dsn, $c['username'], $c['password'], [
            'no-data' => ['sessions', 'cache', 'cache_locks'],
            'add-drop-table' => true,
            'complete-insert' => true,
            'single-transaction' => true,
            'default-character-set' => 'utf8mb4',
            'skip-definer' => true,
        ]);
        $dump->start($path);
    }

    private function manifest(string $type): array
    {
        $files = [];
        StoredFile::query()->orderBy('id')->chunk(1000, function ($chunk) use (&$files) {
            foreach ($chunk as $f) {
                $files[] = [
                    'id' => $f->id, 'uuid' => $f->uuid, 'state' => $f->state, 'drive_file_id' => $f->drive_file_id,
                    'drive_path' => config('smm.drive.root').'/'.$f->drive_folder_path.'/'.$f->drive_name,
                    'original_name' => $f->original_name, 'size' => $f->size, 'sha256' => $f->sha256,
                    'in_zip' => $f->state !== StoredFile::STORED && $f->local_path ? 'files/'.basename($f->local_path) : null,
                ];
            }
        });

        return [
            'app' => config('app.name'),
            'type' => $type,
            'created_at' => now()->toIso8601String(),
            'database' => config('database.connections.'.config('database.default').'.database'),
            'drive_driver' => config('smm.drive.driver'),
            'files' => $files,
        ];
    }

    private function verifyDriveFiles(array $manifest): int
    {
        $missing = 0;
        foreach ($manifest['files'] as $f) {
            if ($f['state'] === StoredFile::STORED && $f['drive_file_id'] && ! $this->drive->metadata($f['drive_file_id'])) {
                $missing++;
            }
        }

        return $missing;
    }

    private function zip(string $zipPath, string $password, string $sql, string $manifestPath, array $manifest): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak bisa membuat ZIP backup.');
        }
        $zip->setPassword($password);
        $entries = ['database.sql' => $sql, 'manifest.json' => $manifestPath];
        foreach ($manifest['files'] as $f) {
            if ($f['in_zip']) {
                $local = storage_path('app/private/pending/'.basename($f['in_zip']));
                if (is_file($local)) {
                    $entries[$f['in_zip']] = $local;
                }
            }
        }
        foreach ($entries as $name => $path) {
            $zip->addFile($path, $name);
            $zip->setEncryptionName($name, ZipArchive::EM_AES_256);
        }
        $zip->addFromString('BACA-SAYA.txt', "Backup SMM PT Artha Safety Indonesia\nDibuat: ".now()->toDateTimeString()."\nIsi terenkripsi AES-256. Gunakan kata sandi backup (SMM_BACKUP_PASSWORD).\nPanduan pemulihan: docs/RESTORE.md\n");
        if (! $zip->close()) {
            throw new RuntimeException('Gagal menulis ZIP backup.');
        }
    }

    private function prune(string $folderId, int $keep): int
    {
        $files = collect($this->drive->listFiles($folderId))
            ->filter(fn ($f) => str_starts_with($f['name'], 'smm-') && str_ends_with($f['name'], '.zip'))
            ->sortByDesc('name')->values();
        $old = $files->slice(max(1, $keep));
        foreach ($old as $f) {
            $this->drive->delete($f['id']);
        }

        return $old->count();
    }

    private function notifyFailure(BackupRun $run, string $reason): void
    {
        $this->notifier->send($this->notifier->usersWithPermission('backup.kelola'), new SmmNotification(
            'Backup SMM bermasalah ('.$run->type.')',
            ['Backup '.$run->type.' pada '.$run->started_at->format('d-m-Y H:i').' bermasalah.', 'Penyebab: '.mb_substr($reason, 0, 500), 'Buka halaman Admin → Backup untuk melihat detail dan menjalankan ulang.'],
            'Buka halaman Backup', route('admin.backup.index'), 'danger'
        ));
    }
}
