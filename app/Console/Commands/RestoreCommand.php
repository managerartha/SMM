<?php

namespace App\Console\Commands;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Backup\RestoreService;
use App\Support\Format;
use Illuminate\Console\Command;

class RestoreCommand extends Command
{
    protected $signature = 'smm:restore
        {--list : Tampilkan daftar backup di Drive}
        {--latest : Pulihkan backup terbaru}
        {--id= : ID berkas backup di Drive}
        {--path= : Pulihkan dari berkas ZIP lokal (diunduh manual dari Drive)}
        {--password= : Kata sandi backup (bawaan: SMM_BACKUP_PASSWORD)}
        {--force : Lewati konfirmasi}';

    protected $description = 'Pulihkan database dari backup (MENIMPA data saat ini). Lihat docs/RESTORE.md';

    public function handle(RestoreService $restore, ActivityLogger $log): int
    {
        if ($this->option('list')) {
            $rows = collect($restore->available())->map(fn ($f) => [$f['type'], $f['name'], number_format($f['size'] / 1024, 0, ',', '.').' KB', $f['id']]);
            $this->table(['Jenis', 'Nama', 'Ukuran', 'ID'], $rows);

            return self::SUCCESS;
        }

        $path = $this->option('path');
        $downloaded = false;
        if (! $path) {
            $id = $this->option('id');
            if (! $id && $this->option('latest')) {
                $latest = $restore->available()[0] ?? null;
                if (! $latest) {
                    $this->error('Tidak ada backup di Drive.');

                    return self::FAILURE;
                }
                $id = $latest['id'];
                $this->info("Backup terbaru: {$latest['name']}");
            }
            if (! $id) {
                $this->error('Pilih --latest, --id=ID, atau --path=berkas.zip (lihat --list).');

                return self::FAILURE;
            }
            $this->info('Mengunduh backup dari Drive…');
            $path = $restore->downloadFromDrive($id);
            $downloaded = true;
        }

        if (! $this->option('force') && ! $this->confirm('SELURUH data database saat ini akan DITIMPA dengan isi backup. Lanjutkan?')) {
            return self::FAILURE;
        }

        try {
            $result = $restore->restoreFromZip($path, (string) ($this->option('password') ?: config('smm.backup.password')));
        } finally {
            if ($downloaded) {
                @unlink($path);
            }
        }

        $log->log('restore', 'Database dipulihkan dari backup '.($result['manifest']['created_at'] ?? '?'), null, ['tables' => $result['tables']]);
        $this->info("Pemulihan selesai: {$result['tables']} tabel, {$result['files_restored']} berkas lokal dipulihkan. Backup dibuat: ".Format::dateTime($result['manifest']['created_at'] ?? null));

        return self::SUCCESS;
    }
}
