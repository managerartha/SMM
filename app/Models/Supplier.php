<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Supplier extends Model
{
    use HasAttachments;

    public const CATEGORIES = ['venue' => 'Venue / tempat', 'katering' => 'Katering', 'percetakan' => 'Percetakan', 'alat' => 'Alat & APD', 'trainer' => 'Trainer / narasumber lepas', 'transportasi' => 'Transportasi', 'atk' => 'ATK & perlengkapan', 'kalibrasi' => 'Kalibrasi / servis', 'lainnya' => 'Lainnya'];
    public const STATUSES = ['aktif' => 'Aktif (disetujui)', 'dievaluasi' => 'Dalam pembinaan', 'tidak_dipakai' => 'Tidak dipakai'];

    protected $fillable = ['number', 'name', 'category', 'contact_person', 'phone', 'email', 'address', 'status', 'notes', 'created_by'];

    public function evaluations(): HasMany
    {
        return $this->hasMany(SupplierEvaluation::class)->orderByDesc('evaluated_at')->orderByDesc('id');
    }

    public function latestEvaluation(): HasOne
    {
        return $this->hasOne(SupplierEvaluation::class)->latestOfMany('evaluated_at');
    }
}
