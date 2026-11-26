<?php

namespace App\Console\Commands;

use App\Domain\Kpi\KpiCalculator;
use Illuminate\Console\Command;

class KpiCalcCommand extends Command
{
    protected $signature = 'smm:kpi-calc {period? : YYYY-MM; kosong = bulan ini & bulan lalu}';

    protected $description = 'Hitung realisasi KPI otomatis dari data SMM (nilai manual tidak ditimpa)';

    public function handle(KpiCalculator $calc): int
    {
        $periods = $this->argument('period') ? [$this->argument('period')] : [now()->subMonthNoOverflow()->format('Y-m'), now()->format('Y-m')];
        foreach ($periods as $p) {
            $this->info("{$p}: ".$calc->run($p).' nilai dihitung.');
        }

        return self::SUCCESS;
    }
}
