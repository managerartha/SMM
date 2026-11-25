<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['remindable_type', 'remindable_id', 'kind', 'target_date', 'sent_at'];

    protected $casts = ['target_date' => 'date', 'sent_at' => 'datetime'];
}
