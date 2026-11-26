<?php

namespace App\Models;

use App\Models\Concerns\HasApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditProgram extends Model
{
    use HasApproval;

    protected $fillable = ['year', 'objective', 'scope', 'criteria', 'status', 'approved_by', 'approved_by_name', 'approved_by_position', 'approved_at', 'approval_note', 'file_id', 'created_by'];

    protected $casts = ['approved_at' => 'datetime'];

    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class)->orderBy('planned_date');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }
}
