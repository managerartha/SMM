<?php

namespace App\Console\Commands;

use App\Domain\Drive\DriveGateway;
use App\Domain\Notifications\Notifier;
use App\Models\StoredFile;
use App\Notifications\SmmNotification;
use App\Support\Settings;
use Illuminate\Console\Command;
use Throwable;

class DriveHealthCommand extends Command
{
    protected $signature = 'smm:drive-health';

    protected $description = 'Periksa koneksi Google Drive & berkas tertunda; email GM bila bermasalah';

    public function handle(DriveGateway $drive, Notifier $notifier): int
    {
        $problems = [];
        try {
            $info = $drive->health();
            Settings::set('drive.last_health', ['ok' => true, 'at' => now()->toIso8601String(), 'account' => $info['account'] ?? null, 'quota' => $info['quota'] ?? null]);
        } catch (Throwable $e) {
            $problems[] = 'Koneksi Google Drive gagal: '.mb_substr($e->getMessage(), 0, 300);
            Settings::set('drive.last_health', ['ok' => false, 'at' => now()->toIso8601String(), 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }
        $stuck = StoredFile::whereIn('state', [StoredFile::PENDING, StoredFile::FAILED])->where('updated_at', '<', now()->subHours(6))->count();
        if ($stuck) {
            $problems[] = "{$stuck} berkas belum berhasil dipindah ke Google Drive lebih dari 6 jam.";
        }

        if ($problems) {
            $notifier->send($notifier->usersWithPermission('backup.kelola'), new SmmNotification(
                'Penyimpanan Google Drive bermasalah', $problems, 'Buka Admin → Google Drive', route('admin.drive.index'), 'danger'
            ));
            foreach ($problems as $p) {
                $this->error($p);
            }

            return self::FAILURE;
        }
        $this->info('Google Drive sehat.');

        return self::SUCCESS;
    }
}
