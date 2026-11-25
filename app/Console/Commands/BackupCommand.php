<?php

namespace App\Console\Commands;

use App\Domain\Backup\BackupService;
use Illuminate\Console\Command;

class BackupCommand extends Command
{
    protected $signature = 'smm:backup {type=harian : harian | bulanan | manual}';

    protected $description = 'Backup database + manifest berkas ke Google Drive (ZIP terenkripsi)';

    public function handle(BackupService $backup): int
    {
        $run = $backup->run($this->argument('type'));
        if ($run->status === 'berhasil') {
            $this->info("Backup berhasil: {$run->file_name} (".number_format($run->size / 1024, 0, ',', '.')." KB, {$run->files_in_manifest} berkas di manifest, {$run->pruned} backup lama dihapus)");
            if ($run->message) {
                $this->warn($run->message);
            }

            return self::SUCCESS;
        }
        $this->error('Backup gagal: '.$run->message);

        return self::FAILURE;
    }
}
