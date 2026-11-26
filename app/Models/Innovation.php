<?php

namespace App\Models;

use App\Models\Concerns\HasApproval;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Innovation extends Model
{
    use HasApproval, HasAttachments;

    public const STATUSES = ['usulan' => 'Usulan', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak', 'pelaksanaan' => 'Pelaksanaan', 'selesai' => 'Selesai'];

    protected $fillable = [
        'number', 'title', 'background', 'objective', 'plan', 'indicator', 'baseline', 'budget', 'division_id', 'proposer_id', 'pic_id',
        'start_date', 'end_date', 'status', 'approved_by', 'approved_by_name', 'approved_by_position', 'approved_at', 'approval_note',
        'result', 'result_value', 'evaluation', 'completed_at', 'created_by',
    ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'approved_at' => 'datetime', 'completed_at' => 'datetime'];

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposer_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function approvalKey(): string
    {
        return $this->budget > 0 ? 'innovation_budget' : 'innovation_no_budget';
    }

    public function statusColor(): string
    {
        return ['usulan' => 'blue', 'disetujui' => 'purple', 'ditolak' => 'red', 'pelaksanaan' => 'amber', 'selesai' => 'green'][$this->status] ?? 'slate';
    }

    public function folderPath(): string
    {
        return 'Inovasi/'.str_replace('/', '-', $this->number);
    }
}
