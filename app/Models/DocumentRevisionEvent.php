<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jejak alur dokumen. Baris "disahkan" adalah bukti pengesahan (nama & jabatan disalin saat itu). */
class DocumentRevisionEvent extends Model
{
    public const UPDATED_AT = null;

    public const LABELS = [
        'dibuat' => 'Dibuat',
        'file_diganti' => 'File diganti',
        'diajukan' => 'Diajukan untuk review',
        'diajukan_pengesahan' => 'Diajukan untuk pengesahan',
        'diteruskan' => 'Review diteruskan ke pengesahan',
        'ditolak_review' => 'Ditolak saat review',
        'disahkan' => 'Disahkan',
        'ditolak_pengesahan' => 'Ditolak saat pengesahan',
        'dibatalkan' => 'Dibatalkan',
        'kedaluwarsa' => 'Kedaluwarsa (digantikan revisi baru)',
    ];

    protected $fillable = ['document_revision_id', 'action', 'actor_id', 'actor_name', 'actor_position', 'note'];

    protected $casts = ['created_at' => 'datetime'];

    public function revision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'document_revision_id');
    }

    public function label(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }
}
