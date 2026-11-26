<?php

namespace App\Domain\Kpi;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Numbering\NumberGenerator;
use App\Models\Kpi;
use App\Models\KpiValue;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class KpiService
{
    public function __construct(private NumberGenerator $numbers, private ActivityLogger $log) {}

    public function create(User $user, array $data): Kpi
    {
        $kpi = Kpi::create($data + ['code' => $this->numbers->next('kpi', [], $user->id), 'created_by' => $user->id]);
        $this->log->log('buat', "Menambah sasaran mutu/KPI {$kpi->code}: {$kpi->name}", $kpi, [], $user);

        return $kpi;
    }

    public function update(User $user, Kpi $kpi, array $data): void
    {
        $kpi->update($data);
        $this->log->log('ubah', "Mengubah KPI {$kpi->code}", $kpi, [], $user);
    }

    public function canEnter(User $user, Kpi $kpi): bool
    {
        return $user->can('kpi.kelola') || ($user->can('kpi.isi') && ($kpi->owner_id === $user->id || ($kpi->division_id && $kpi->division_id === $user->division_id)));
    }

    public function setValue(User $user, Kpi $kpi, string $period, float $value, ?string $note): KpiValue
    {
        if (! $this->canEnter($user, $kpi)) {
            abort(403);
        }
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) || (int) substr($period, 0, 4) !== $kpi->year) {
            throw ValidationException::withMessages(['period' => 'Periode harus bulan di tahun '.$kpi->year.'.']);
        }
        $v = KpiValue::updateOrCreate(['kpi_id' => $kpi->id, 'period' => $period], ['value' => $value, 'is_auto' => false, 'note' => $note, 'entered_by' => $user->id]);
        $this->log->log('ubah', "Mengisi realisasi {$kpi->code} {$period}: {$value} {$kpi->unit}", $kpi, [], $user);

        return $v;
    }
}
