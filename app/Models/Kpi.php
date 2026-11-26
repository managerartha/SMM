<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Sasaran mutu & KPI: target tahunan, realisasi bulanan. */
class Kpi extends Model
{
    protected $fillable = ['code', 'name', 'objective', 'unit', 'target', 'comparator', 'source', 'division_id', 'owner_id', 'year', 'is_active', 'created_by'];

    protected $casts = ['target' => 'float', 'is_active' => 'boolean'];

    public function values(): HasMany
    {
        return $this->hasMany(KpiValue::class)->orderBy('period');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function achieved(?float $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return $this->comparator === '<=' ? $value <= $this->target : $value >= $this->target;
    }

    public function targetLabel(): string
    {
        return ($this->comparator === '<=' ? '≤ ' : '≥ ').rtrim(rtrim(number_format($this->target, 2, ',', '.'), '0'), ',').' '.$this->unit;
    }
}
