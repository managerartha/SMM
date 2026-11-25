<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Kategori bukti 1–16 untuk Paket Bukti (Tahap 4). */
class EvidenceCategory extends Model
{
    public $incrementing = false;

    protected $fillable = ['id', 'name', 'folder_name'];

    public function label(): string
    {
        return $this->id.'. '.$this->name;
    }
}
