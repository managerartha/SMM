<?php

namespace App\Domain\Suppliers;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Numbering\NumberGenerator;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use App\Models\User;

class SupplierService
{
    public function __construct(private NumberGenerator $numbers, private ActivityLogger $log) {}

    public function create(User $user, array $data): Supplier
    {
        $s = Supplier::create($data + ['number' => $this->numbers->next('supplier', [], $user->id), 'created_by' => $user->id]);
        $this->log->log('buat', "Menambah pemasok {$s->number} – {$s->name}", $s, [], $user);

        return $s;
    }

    public function update(User $user, Supplier $s, array $data): void
    {
        $s->update($data);
        $this->log->log('ubah', "Mengubah pemasok {$s->number}", $s, [], $user);
    }

    public function evaluate(User $user, Supplier $s, array $data): SupplierEvaluation
    {
        $avg = round(($data['quality'] + $data['timeliness'] + $data['price'] + $data['service']) / 4, 2);
        $e = $s->evaluations()->create($data + ['average' => $avg, 'evaluated_by' => $user->id, 'evaluated_at' => $data['evaluated_at'] ?? now()->toDateString()]);
        $s->update(['status' => ['dipertahankan' => 'aktif', 'dibina' => 'dievaluasi', 'diganti' => 'tidak_dipakai'][$data['decision']]]);
        $this->log->log('verifikasi', "Evaluasi pemasok {$s->number} periode {$e->period}: rata-rata {$avg}, {$e->decision}", $s, [], $user);

        return $e;
    }
}
