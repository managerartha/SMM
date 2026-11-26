<?php

namespace App\Models;

use App\Models\Concerns\HasApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReport extends Model
{
    use HasApproval;

    protected $fillable = ['number', 'type', 'period', 'data', 'analysis', 'status', 'approved_by', 'approved_by_name', 'approved_by_position', 'approved_at', 'approval_note', 'file_id', 'created_by'];

    protected $casts = ['data' => 'array', 'approved_at' => 'datetime'];

    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvalKey(): string
    {
        return $this->type === 'tahunan' ? 'performance_report_annual' : 'performance_report_monthly';
    }

    public function periodLabel(): string
    {
        if ($this->type === 'tahunan') {
            return 'Tahun '.$this->period;
        }
        $m = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return $m[(int) substr($this->period, 5, 2) - 1].' '.substr($this->period, 0, 4);
    }
}
