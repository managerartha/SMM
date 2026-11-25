<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalDocument extends Model
{
    protected $fillable = [
        'number', 'external_document_category_id', 'title', 'issuer', 'evidence_category_id', 'notes',
        'current_version_id', 'created_by', 'archived_at', 'archived_by',
    ];

    protected $casts = ['archived_at' => 'datetime'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExternalDocumentCategory::class, 'external_document_category_id');
    }

    public function evidenceCategory(): BelongsTo
    {
        return $this->belongsTo(EvidenceCategory::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ExternalDocumentVersion::class)->orderByDesc('version_no');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ExternalDocumentVersion::class, 'current_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function scopeNotArchived(Builder $q): Builder
    {
        return $q->whereNull('archived_at');
    }
}
