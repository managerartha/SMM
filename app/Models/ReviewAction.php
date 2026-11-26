<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewAction extends Model
{
    protected $fillable = ['management_review_id', 'description', 'pic_id', 'due_date', 'status', 'result', 'completed_at'];

    protected $casts = ['due_date' => 'date', 'completed_at' => 'datetime'];

    public function review(): BelongsTo
    {
        return $this->belongsTo(ManagementReview::class, 'management_review_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }
}
