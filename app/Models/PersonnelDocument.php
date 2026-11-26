<?php

namespace App\Models;

use App\Domain\ExternalDocuments\ValidityStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelDocument extends Model
{
    public const CATEGORIES = ['cv' => 'CV / daftar riwayat hidup', 'ijazah' => 'Ijazah', 'sertifikat' => 'Sertifikat kompetensi / pelatihan', 'lisensi' => 'Lisensi K3 (SKP/SIO dll.)', 'sk' => 'SK penunjukan', 'kontrak' => 'Kontrak kerja', 'ktp' => 'KTP / identitas', 'lainnya' => 'Lainnya'];

    protected $fillable = ['personnel_id', 'category', 'title', 'original_number', 'issuer', 'issued_at', 'expires_at', 'file_id', 'verified_by', 'verified_at', 'replaced_by_id', 'created_by'];

    protected $casts = ['issued_at' => 'date', 'expires_at' => 'date', 'verified_at' => 'datetime'];

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function validity(): ValidityStatus
    {
        return ValidityStatus::for($this->expires_at);
    }
}
