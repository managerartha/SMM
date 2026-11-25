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
        $this->info("Pengingat terkirim: {$r['external']} dokumen eksternal, {$r['review']} tinjauan dokumen.");

        return self::SUCCESS;
    }
}
