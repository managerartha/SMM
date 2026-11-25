<?php

namespace App\Console\Commands;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Notifications\Notifier;
use App\Notifications\SmmNotification;
use Illuminate\Console\Command;

class VerifyLogCommand extends Command
{
    protected $signature = 'smm:verify-log';

    protected $description = 'Periksa keutuhan rantai hash log aktivitas (email GM bila ada perubahan)';

    public function handle(ActivityLogger $log, Notifier $notifier): int
    {
        $broken = $log->verify();
        if ($broken === []) {
            $this->info('Log aktivitas utuh.');

            return self::SUCCESS;
        }
        $ids = implode(', ', array_slice($broken, 0, 50));
        $notifier->send($notifier->usersWithPermission('pengguna.kelola'), new SmmNotification(
            'PERINGATAN: log aktivitas SMM berubah',
            ['Pemeriksaan rantai hash menemukan baris log yang diubah/dihapus langsung di database.', "ID baris: {$ids}", 'Periksa akses database (phpMyAdmin) dan pulihkan dari backup bila perlu.'],
            'Buka log aktivitas', route('activity.index'), 'danger'
        ));
        $this->error('Log aktivitas TIDAK utuh. Baris bermasalah (ID): '.$ids);

        return self::FAILURE;
    }
}
