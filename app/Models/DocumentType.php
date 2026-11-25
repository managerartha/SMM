<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentType extends Model
{
    protected $fillable = ['code', 'name', 'review_required', 'review_interval_months', 'default_evidence_category_id', 'sort', 'is_active'];

    protected $casts = ['review_required' => 'boolean', 'is_active' => 'boolean'];

    public function defaultEvidenceCategory(): BelongsTo
    {
        return $this->belongsTo(EvidenceCategory::class, 'default_evidence_category_id');
    }

    public function approvalSubjectKey(): string
    {
        return 'document:'.$this->code;
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('sort');
    }
}
