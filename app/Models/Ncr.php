<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ncr extends Model
{
    use HasAttachments;

    public const OPEN = 'terbuka';
    public const ACTION = 'tindakan';
    public const MONITORING = 'pemantauan';
    public const CLOSING = 'menunggu_penutupan';
    public const CLOSED = 'ditutup';
    public const CANCELLED = 'dibatalkan';

    public const STATUSES = [
        self::OPEN => 'Terbuka (koreksi & analisis)',
        self::ACTION => 'Tindakan korektif',
        self::MONITORING => 'Pemantauan efektivitas',
        self::CLOSING => 'Menunggu penutupan',
        self::CLOSED => 'Ditutup',
        self::CANCELLED => 'Dibatalkan',
    ];

    public const SOURCES = [
        'keluhan' => 'Keluhan / feedback', 'audit_internal' => 'Audit internal', 'temuan_internal' => 'Temuan internal',
        'survei' => 'Hasil survei', 'pemasok' => 'Pemasok / mitra', 'eksternal' => 'Audit / penilaian eksternal', 'lainnya' => 'Lainnya',
    ];

    public const METHODS = ['5why' => '5 Why', 'fishbone' => 'Diagram tulang ikan (fishbone)', 'lainnya' => 'Lainnya'];

    protected $fillable = [
        'number', 'source', 'complaint_id', 'title', 'description', 'requirement', 'division_id', 'severity', 'found_at', 'pic_id',
        'target_date', 'status', 'correction', 'correction_at', 'root_cause_method', 'root_cause', 'root_cause_at',
        'verification_note', 'verified_at', 'verified_by', 'closure_note', 'closed_at', 'closed_by', 'closed_by_name',
        'closed_by_position', 'reopened_count', 'cancel_reason', 'evidence_category_id', 'created_by',
    ];

    protected $casts = [
        'found_at' => 'date', 'target_date' => 'date', 'correction_at' => 'datetime', 'root_cause_at' => 'datetime',
        'verified_at' => 'datetime', 'closed_at' => 'datetime',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(NcrAction::class)->orderBy('cycle')->orderBy('id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(NcrEffectivenessCheck::class)->orderBy('cycle')->orderBy('day');
    }

    public function events(): HasMany
    {
        return $this->hasMany(NcrEvent::class)->orderBy('id');
    }

    public function currentCycle(): int
    {
        return $this->reopened_count + 1;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::CLOSED, self::CANCELLED], true);
    }

    public function isOverdue(): bool
    {
        return ! $this->isClosed() && in_array($this->status, [self::OPEN, self::ACTION], true)
            && $this->target_date && $this->target_date->lt(now()->startOfDay());
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return [self::OPEN => 'red', self::ACTION => 'amber', self::MONITORING => 'blue', self::CLOSING => 'purple', self::CLOSED => 'green', self::CANCELLED => 'slate'][$this->status] ?? 'slate';
    }

    public function folderPath(): string
    {
        return 'NCR/'.str_replace('/', '-', $this->number);
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->can('ncr.lihat-semua') || $user->can('ncr.kelola')) {
            return $q;
        }

        return $q->where(function (Builder $w) use ($user) {
            $w->where('pic_id', $user->id)->orWhere('created_by', $user->id)
                ->orWhereHas('actions', fn ($a) => $a->where('pic_id', $user->id))
                // Pencatat keluhan boleh memantau NCR tindak lanjutnya (baca saja).
                ->orWhereHas('complaint', fn ($c) => $c->where('created_by', $user->id));
            if ($user->can('ncr.kelola-divisi') && $user->division_id) {
                $w->orWhere('division_id', $user->division_id);
            }
        });
    }
}
