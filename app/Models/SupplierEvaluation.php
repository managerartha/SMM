<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierEvaluation extends Model
{
    public const DECISIONS = ['dipertahankan' => 'Dipertahankan', 'dibina' => 'Dibina / diberi peringatan', 'diganti' => 'Diganti'];

    protected $fillable = ['supplier_id', 'period', 'quality', 'timeliness', 'price', 'service', 'average', 'decision', 'notes', 'evaluated_by', 'evaluated_at'];

    protected $casts = ['evaluated_at' => 'date', 'average' => 'float'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
