<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Log aktivitas: hanya boleh ditambah. Ubah/hapus lewat model akan ditolak.
 * Penulisan hanya lewat App\Domain\Activity\ActivityLogger.
 */
class ActivityLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['occurred_at' => 'datetime', 'properties' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Log aktivitas tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Log aktivitas tidak boleh dihapus.'));
    }

    public const ACTIONS = [
        'login' => 'Login',
        'login_gagal' => 'Login gagal',
        'logout' => 'Logout',
        'unggah' => 'Unggah',
        'buat' => 'Buat',
        'ubah' => 'Ubah',
        'ajukan' => 'Ajukan',
        'teruskan' => 'Teruskan review',
        'setujui' => 'Setujui / sahkan',
        'tolak' => 'Tolak',
        'batal' => 'Batalkan',
        'unduh' => 'Unduh',
        'arsip' => 'Arsip',
        'pulihkan' => 'Pulihkan dari arsip',
        'verifikasi' => 'Verifikasi',
        'perpanjang' => 'Perpanjang',
        'nonaktifkan' => 'Nonaktifkan',
        'aktifkan' => 'Aktifkan',
        'pengaturan' => 'Ubah pengaturan',
        'backup' => 'Backup',
        'restore' => 'Restore',
        'ekspor' => 'Ekspor',
        'sistem' => 'Sistem',
    ];

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }
}
