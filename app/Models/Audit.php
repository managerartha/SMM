<?php

namespace App\Models;

use App\Models\Concerns\HasApproval;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Audit extends Model
{
    use HasApproval, HasAttachments;

    public const STATUSES = ['rencana' => 'Rencana', 'pelaksanaan' => 'Pelaksanaan', 'pengesahan' => 'Menunggu pengesahan laporan', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];

    protected $fillable = [
        'number', 'audit_program_id', 'division_id', 'process', 'scope', 'criteria', 'planned_date', 'auditor_id', 'auditee_id', 'status',
        'executed_at', 'conclusion', 'approved_by', 'approved_by_name', 'approved_by_position', 'approved_at', 'approval_note', 'file_id',
        'cancel_reason', 'created_by',
    ];

    protected $casts = ['planned_date' => 'date', 'executed_at' => 'date', 'approved_at' => 'datetime'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(AuditProgram::class, 'audit_program_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function auditee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditee_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AuditChecklistItem::class)->orderBy('sort')->orderBy('id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class)->orderBy('id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }

    public function statusColor(): string
    {
        return ['rencana' => 'blue', 'pelaksanaan' => 'amber', 'pengesahan' => 'purple', 'selesai' => 'green', 'dibatalkan' => 'slate'][$this->status] ?? 'slate';
    }

    public function folderPath(): string
    {
        return 'Audit Internal/'.$this->planned_date->format('Y').'/'.str_replace('/', '-', $this->number);
    }
}
