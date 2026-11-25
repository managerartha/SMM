<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRun extends Model
{
    public $timestamps = false;

    protected $fillable = ['type', 'status', 'started_at', 'finished_at', 'file_name', 'drive_file_id', 'size', 'files_in_manifest', 'files_missing', 'pruned', 'message', 'triggered_by'];

    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
