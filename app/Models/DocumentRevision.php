<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentRevision extends Model
{
    public const DRAFT = 'draf';
    public const REVIEW = 'review';
    public const APPROVAL = 'pengesahan';
    public const EFFECTIVE = 'berlaku';
    public const EXPIRED = 'kedaluwarsa';
    public const CANCELLED = 'dibatalkan';

    public const OPEN_STATUSES = [self::DRAFT, self::REVIEW, self::APPROVAL];

    public const LABELS = [
        self::DRAFT => 'Draf',
        self::REVIEW => 'Review',
        self::APPROVAL => 'Menunggu Pengesahan',
        self::EFFECTIVE => 'Berlaku',
        self::EXPIRED => 'Kedaluwarsa',
        self::CANCELLED => 'Dibatalkan',
    ];

    protected $fillable = [
        'document_id', 'revision_no', 'status', 'change_reason', 'change_summary', 'pdf_file_id', 'source_file_id',
        'created_by', 'reviewer_id', 'submitted_at', 'reviewed_at', 'approved_by', 'approved_by_name',
        'approved_by_position', 'approved_at', 'effective_date', 'expired_at', 'superseded_by_id',
    ];

    protected $casts = [
        'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime',
        'effective_date' => 'date', 'expired_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function pdfFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'pdf_file_id');
    }

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'source_file_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DocumentRevisionEvent::class)->orderBy('id');
    }

    public function label(): string
    {
        return sprintf('Rev. %02d', $this->revision_no);
    }

    public function fullLabel(): string
    {
        return $this->document->number.' '.$this->label();
    }

    public function statusLabel(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
