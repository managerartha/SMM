<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityLog extends Model
{
    use HasAttachments;

    public const TYPES = ['pemeriksaan' => 'Pemeriksaan berkala', 'kalibrasi' => 'Kalibrasi', 'perbaikan' => 'Perbaikan', 'lainnya' => 'Lainnya'];

    protected $fillable = ['facility_id', 'type', 'performed_at', 'condition_after', 'result', 'cost', 'supplier_id', 'valid_until', 'performed_by'];

    protected $casts = ['performed_at' => 'date', 'valid_until' => 'date'];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
