<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NcrEffectivenessCheck extends Model
{
    use HasAttachments;

    protected $fillable = ['ncr_id', 'cycle', 'day', 'due_date', 'result', 'note', 'checked_at', 'checked_by'];

    protected $casts = ['due_date' => 'date', 'checked_at' => 'datetime'];

    public function ncr(): BelongsTo
    {
        return $this->belongsTo(Ncr::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    /** Boleh diisi mulai 7 hari sebelum jadwal. */
    public function isOpenForInput(): bool
    {
        return $this->result === null && now()->startOfDay()->gte($this->due_date->copy()->subDays(7));
    }
}
