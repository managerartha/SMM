<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Berkas unggahan. Isi berkas ada di Google Drive (state=stored) atau
 * sementara di server (state=pending, menunggu dipindah oleh antrian).
 */
class StoredFile extends Model
{
    public const PENDING = 'pending';
    public const STORED = 'stored';
    public const FAILED = 'failed';

    protected $table = 'files';

    protected $fillable = [
        'uuid', 'original_name', 'extension', 'mime', 'size', 'sha256', 'md5', 'state', 'local_path',
        'drive_folder_path', 'drive_name', 'drive_file_id', 'attempts', 'last_error', 'stored_at',
        'attachable_type', 'attachable_id', 'purpose', 'uploaded_by',
    ];

    protected $casts = ['stored_at' => 'datetime'];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isPdf(): bool
    {
        return $this->extension === 'pdf';
    }

    public function sizeLabel(): string
    {
        $s = $this->size;

        return $s >= 1048576 ? number_format($s / 1048576, 1, ',', '.').' MB' : number_format(max(1, $s / 1024), 0, ',', '.').' KB';
    }
}
