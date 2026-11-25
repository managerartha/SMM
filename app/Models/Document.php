<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    public const SCOPE_PUBLIC = 'umum';
    public const SCOPE_RESTRICTED = 'terbatas';

    protected $fillable = [
        'number', 'document_type_id', 'division_id', 'title', 'summary', 'owner_id', 'evidence_category_id',
        'access_scope', 'current_revision_id', 'next_review_date', 'archived_at', 'archived_by',
    ];

    protected $casts = ['next_review_date' => 'date', 'archived_at' => 'datetime'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function evidenceCategory(): BelongsTo
    {
        return $this->belongsTo(EvidenceCategory::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class)->orderByDesc('revision_no');
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'current_revision_id');
    }

    /** Revisi yang sedang dikerjakan (draf / review / pengesahan), paling banyak satu. */
    public function openRevision(): HasOne
    {
        return $this->hasOne(DocumentRevision::class)->whereIn('status', DocumentRevision::OPEN_STATUSES);
    }

    public function accessRules(): HasMany
    {
        return $this->hasMany(DocumentAccess::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function statusLabel(): string
    {
        if ($this->isArchived()) {
            return 'Diarsip';
        }
        if ($this->current_revision_id) {
            return 'Berlaku';
        }

        return $this->openRevision?->statusLabel() ?? 'Draf';
    }

    public function scopeNotArchived(Builder $q): Builder
    {
        return $q->whereNull('archived_at');
    }

    /**
     * Dokumen yang boleh dilihat pengguna tanpa izin "lihat semua":
     * cakupan umum, divisi pemilik = divisi pengguna, atau tercantum di daftar akses (divisi/peran).
     */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->can('dokumen.lihat-semua')) {
            return $q;
        }

        $roles = $user->getRoleNames()->all();

        return $q->where(function (Builder $w) use ($user, $roles) {
            $w->where('access_scope', self::SCOPE_PUBLIC)
                ->when($user->division_id, fn ($x) => $x->orWhere('division_id', $user->division_id))
                ->orWhereHas('accessRules', function (Builder $a) use ($user, $roles) {
                    $a->where(function (Builder $b) use ($user, $roles) {
                        $b->whereIn('role_name', $roles ?: ['-']);
                        if ($user->division_id) {
                            $b->orWhere('division_id', $user->division_id);
                        }
                    });
                });
        });
    }
}
