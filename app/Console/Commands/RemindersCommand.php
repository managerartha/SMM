<?php

namespace App\Console\Commands;

use App\Domain\Notifications\Reminders;
use Illuminate\Console\Command;

class RemindersCommand extends Command
{
    protected $signature = 'smm:reminders';

    protected $description = 'Kirim pengingat masa berlaku dokumen eksternal & jadwal tinjauan dokumen';

    public function handle(Reminders $reminders): int
    {
        $r = $reminders->run();
        $this->info('Pengingat terkirim: '.collect($r)->map(fn ($n, $k) => "{$k}={$n}")->implode(', '));

        return self::SUCCESS;
    }
}
