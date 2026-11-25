<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalDocumentCategory extends Model
{
    protected $fillable = ['code', 'name', 'default_evidence_category_id', 'sort', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function defaultEvidenceCategory(): BelongsTo
    {
        return $this->belongsTo(EvidenceCategory::class, 'default_evidence_category_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('sort');
    }
}
