<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catatan mutu: bukti pelaksanaan (formulir terisi, daftar hadir, laporan trainer, foto, notulen) dengan masa simpan. */
class QualityRecord extends Model
{
    use HasAttachments;

    protected $fillable = [
        'number', 'document_id', 'training_class_id', 'division_id', 'title', 'description', 'activity_date', 'retention_years',
        'retention_until', 'evidence_category_id', 'created_by', 'archived_at',
    ];

    protected $casts = ['activity_date' => 'date', 'retention_until' => 'date', 'archived_at' => 'datetime'];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function trainingClass(): BelongsTo
    {
        return $this->belongsTo(TrainingClass::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function evidenceCategory(): BelongsTo
    {
        return $this->belongsTo(EvidenceCategory::class);
    }

    public function isPastRetention(): bool
    {
        return $this->retention_until->lt(now()->startOfDay());
    }

    public function folderPath(): string
    {
        return 'Catatan Mutu/'.$this->activity_date->format('Y').'/'.str_replace('/', '-', $this->number);
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->can('catatan.lihat-semua')) {
            return $q;
        }

        return $q->where(fn (Builder $w) => $w->where('created_by', $user->id)
            ->when($user->division_id, fn ($x) => $x->orWhere('division_id', $user->division_id)));
    }
}
