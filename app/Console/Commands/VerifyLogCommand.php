<?php

namespace App\Console\Commands;

use App\Domain\Activity\ActivityLogger;
use Illuminate\Console\Command;

class VerifyLogCommand extends Command
{
    protected $signature = 'smm:verify-log';

    protected $description = 'Periksa keutuhan rantai hash log aktivitas';

    public function handle(ActivityLogger $log): int
    {
        $broken = $log->verify();
        if ($broken === []) {
            $this->info('Log aktivitas utuh.');

            return self::SUCCESS;
        }
        $this->error('Log aktivitas TIDAK utuh. Baris bermasalah (ID): '.implode(', ', array_slice($broken, 0, 50)));

        return self::FAILURE;
    }
}
