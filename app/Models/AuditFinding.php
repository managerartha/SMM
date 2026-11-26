<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditFinding extends Model
{
    public const TYPES = ['mayor' => 'Ketidaksesuaian mayor', 'minor' => 'Ketidaksesuaian minor', 'observasi' => 'Observasi', 'peluang' => 'Peluang perbaikan'];

    protected $fillable = ['audit_id', 'type', 'clause', 'description', 'ncr_id', 'created_by'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function ncr(): BelongsTo
    {
        return $this->belongsTo(Ncr::class);
    }
}
