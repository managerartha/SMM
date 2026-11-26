<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kolom pengesahan bersama: approved_by/_name/_position/_at (nama & jabatan disalin saat disahkan sebagai bukti). */
trait HasApproval
{
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function recordApproval(User $user, ?string $note = null): array
    {
        return ['approved_by' => $user->id, 'approved_by_name' => $user->name, 'approved_by_position' => $user->position, 'approved_at' => now(), 'approval_note' => $note];
    }
}
