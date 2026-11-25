<?php

namespace App\Models;

use App\Domain\ExternalDocuments\ValidityStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalDocumentVersion extends Model
{
    protected $fillable = [
        'external_document_id', 'version_no', 'original_number', 'issued_at', 'expires_at', 'file_id',
        'verified_by', 'verified_at', 'notes', 'created_by',
    ];

    protected $casts = ['issued_at' => 'date', 'expires_at' => 'date', 'verified_at' => 'datetime'];

    public function externalDocument(): BelongsTo
    {
        return $this->belongsTo(ExternalDocument::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validity(): ValidityStatus
    {
        return ValidityStatus::for($this->expires_at);
    }

    public function daysLeft(): ?int
    {
        return $this->expires_at ? (int) now()->startOfDay()->diffInDays($this->expires_at, false) : null;
    }
}
