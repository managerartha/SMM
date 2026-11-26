<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NcrAction extends Model
{
    use HasAttachments;

    protected $fillable = ['ncr_id', 'cycle', 'description', 'pic_id', 'due_date', 'status', 'completed_at', 'completion_note', 'completed_by', 'created_by'];

    protected $casts = ['due_date' => 'date', 'completed_at' => 'datetime'];

    public function ncr(): BelongsTo
    {
        return $this->belongsTo(Ncr::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'rencana' && $this->due_date->lt(now()->startOfDay());
    }
}
