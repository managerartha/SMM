<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidencePackage extends Model
{
    protected $fillable = ['date_from', 'date_to', 'status', 'summary', 'error', 'file_id', 'created_by', 'finished_at'];

    protected $casts = ['date_from' => 'date', 'date_to' => 'date', 'summary' => 'array', 'finished_at' => 'datetime'];

    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
