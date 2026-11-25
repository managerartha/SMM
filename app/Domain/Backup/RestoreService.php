<?php

namespace App\Domain\Backup;

use App\Domain\Drive\DriveFolders;
use App\Domain\Drive\DriveGateway;
use App\Jobs\PushFileToDrive;
use App\Models\StoredFile;
use App\Support\Settings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Pemulihan dari backup (lihat docs/RESTORE.md).
 * PERINGATAN: menimpa seluruh isi database saat ini.
 */
class RestoreService
{
    public function __construct(private DriveGateway $drive, private DriveFolders $folders) {}

    /** Daftar backup di Drive, terbaru dulu. */
    public function available(): array
    {
        $all = [];
        foreach (BackupService::FOLDERS as $type => $folder) {
            $id = $this->folders->idFor(config('smm.drive.backup_root'), $folder);
            foreach ($this->drive->listFiles($id) as $f) {
                if (str_ends_with($f['name'], '.zip')) {
                    $all[] = $f + ['type' => $type];
                }
            }
        }
        usort($all, fn ($a, $b) => strcmp(substr($b['name'], -21), substr($a['name'], -21)));

        return $all;
    }

    public function downloadFromDrive(string $fileId): string
    {
        $path = storage_path('app/private/tmp/restore-'.Str::random(12).'.zip');
        $this->drive->downloadTo($fileId, $path);

        return $path;
    }

    /** @return array{tables:int,files_restored:int,manifest:array} */
    public function restoreFromZip(string $zipPath, string $password): array
    {
        $work = storage_path('app/private/tmp/restore-'.Str::random(12));
        File::ensureDirectoryExists($work, 0700);
        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('Berkas backup tidak bisa dibuka (bukan ZIP yang valid).');
            }
            $zip->setPassword($password);
            if (! $zip->extractTo($work)) {
                $zip->close();
                throw new RuntimeException('Gagal mengekstrak backup. Periksa kata sandi backup.');
            }
            $zip->close();

            $sql = $work.'/database.sql';
            $manifestPath = $work.'/manifest.json';
            if (! is_file($sql) || ! is_file($manifestPath)) {
                throw new RuntimeException('Isi backup tidak lengkap (database.sql / manifest.json tidak ada).');
            }
            $manifest = json_decode(file_get_contents($manifestPath), true);

            Schema::disableForeignKeyConstraints();
            Schema::dropAllTables();
            $tables = $this->importSql($sql);
            Schema::enableForeignKeyConstraints();

            // Bila backup berasal dari versi aplikasi lama, migrasi menyusulkan struktur terbaru.
            Artisan::call('migrate', ['--force' => true]);

            // Pulihkan berkas yang belum sempat pindah ke Drive saat backup dibuat.
            $restored = 0;
            File::ensureDirectoryExists(storage_path('app/private/pending'), 0700);
            foreach ($manifest['files'] ?? [] as $f) {
                if (! empty($f['in_zip']) && is_file($work.'/'.$f['in_zip'])) {
                    File::copy($work.'/'.$f['in_zip'], storage_path('app/private/pending/'.basename($f['in_zip'])));
                    $restored++;
                    if ($file = StoredFile::find($f['id'])) {
                        $file->update(['state' => StoredFile::PENDING, 'attempts' => 0]);
                        PushFileToDrive::dispatch($file->id);
                    }
                }
            }

            // ID folder Drive di cache bisa berbeda (mis. pindah dari lokal ke Google) — buang dan bangun ulang.
            DB::table('drive_folders')->delete();
            Settings::flush();

            return ['tables' => $tables, 'files_restored' => $restored, 'manifest' => $manifest];
        } finally {
            File::deleteDirectory($work);
        }
    }

    /** Impor SQL baris demi baris (hasil mysqldump-php: satu pernyataan diakhiri ';' di akhir baris). */
    private function importSql(string $path): int
    {
        $pdo = DB::connection()->getPdo();
        $fh = fopen($path, 'rb');
        $buffer = '';
        $tables = 0;
        while (($line = fgets($fh)) !== false) {
            $trim = trim($line);
            if ($buffer === '' && ($trim === '' || str_starts_with($trim, '--'))) {
                continue;
            }
            $buffer .= $line;
            if (str_ends_with($trim, ';')) {
                if (stripos(ltrim($buffer), 'CREATE TABLE') === 0) {
                    $tables++;
                }
                $pdo->exec($buffer);
                $buffer = '';
            }
        }
        fclose($fh);
        if (trim($buffer) !== '') {
            $pdo->exec($buffer);
        }

        return $tables;
    }
}
