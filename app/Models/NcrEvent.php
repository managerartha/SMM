<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NcrEvent extends Model
{
    public const UPDATED_AT = null;

    public const LABELS = [
        'dibuat' => 'NCR dibuat', 'koreksi' => 'Koreksi dicatat', 'akar_masalah' => 'Analisis akar masalah dicatat',
        'tindakan_ditambah' => 'Tindakan korektif ditambahkan', 'mulai_tindakan' => 'Tindakan korektif dimulai',
        'tindakan_selesai' => 'Tindakan korektif selesai', 'diverifikasi' => 'Pelaksanaan diverifikasi, pemantauan dimulai',
        'efektivitas' => 'Pemantauan efektivitas dicatat', 'tidak_efektif' => 'Tidak efektif, NCR dibuka ulang',
        'ditutup' => 'NCR ditutup', 'penutupan_ditolak' => 'Penutupan ditolak', 'dibatalkan' => 'NCR dibatalkan', 'diubah' => 'Data diubah',
    ];

    protected $fillable = ['ncr_id', 'action', 'actor_id', 'actor_name', 'actor_position', 'note'];

    protected $casts = ['created_at' => 'datetime'];

    public function label(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }
}
