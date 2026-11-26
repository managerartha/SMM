<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    use HasAttachments;

    public const TYPES = ['keluhan' => 'Keluhan', 'feedback' => 'Feedback / apresiasi', 'saran' => 'Saran / usulan perbaikan'];
    public const SOURCES = ['peserta' => 'Peserta pelatihan', 'klien' => 'Klien perusahaan', 'internal' => 'Internal (karyawan)', 'survei' => 'Survei', 'pemasok' => 'Pemasok / mitra', 'lainnya' => 'Lainnya'];
    public const CHANNELS = ['whatsapp' => 'WhatsApp', 'email' => 'Email', 'telepon' => 'Telepon', 'langsung' => 'Langsung / tatap muka', 'formulir' => 'Formulir', 'lms' => 'LMS', 'lainnya' => 'Lainnya'];
    public const CATEGORIES = ['pelayanan' => 'Pelayanan / CS', 'materi' => 'Materi', 'trainer' => 'Trainer / instruktur', 'sarana' => 'Sarana & prasarana', 'jadwal' => 'Jadwal', 'administrasi' => 'Administrasi / pembayaran', 'sertifikat' => 'Sertifikat', 'lms' => 'LMS / sistem', 'lainnya' => 'Lainnya'];
    public const SEVERITIES = ['rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi'];
    public const STATUSES = ['baru' => 'Baru', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];

    protected $fillable = [
        'number', 'type', 'source', 'channel', 'received_at', 'reporter_name', 'reporter_contact', 'reporter_org', 'class_code',
        'category', 'division_id', 'subject', 'description', 'severity', 'pic_id', 'response_due_date', 'status', 'response',
        'responded_at', 'resolution', 'closed_at', 'closed_by', 'ncr_id', 'evidence_category_id', 'created_by', 'external_id',
    ];

    protected $casts = ['received_at' => 'date', 'response_due_date' => 'date', 'responded_at' => 'datetime', 'closed_at' => 'datetime'];

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

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function ncr(): BelongsTo
    {
        return $this->belongsTo(Ncr::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['baru', 'diproses'], true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->response_due_date && $this->response_due_date->lt(now()->startOfDay());
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return ['baru' => 'blue', 'diproses' => 'amber', 'selesai' => 'green', 'dibatalkan' => 'slate'][$this->status] ?? 'slate';
    }

    /** Yang boleh dilihat: semua (izin lihat-semua) atau yang dibuat / ditugaskan / divisinya (penangan divisi). */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->can('keluhan.lihat-semua') || $user->can('keluhan.kelola')) {
            return $q;
        }

        return $q->where(function (Builder $w) use ($user) {
            $w->where('created_by', $user->id)->orWhere('pic_id', $user->id);
            if ($user->can('keluhan.kelola-divisi') && $user->division_id) {
                $w->orWhere('division_id', $user->division_id);
            }
        });
    }
}
