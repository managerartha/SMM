<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditChecklistItem extends Model
{
    public const RESULTS = ['sesuai' => 'Sesuai', 'tidak_sesuai' => 'Tidak sesuai', 'observasi' => 'Observasi', 'na' => 'Tidak berlaku'];

    protected $fillable = ['audit_id', 'sort', 'clause', 'question', 'result', 'evidence'];
}
