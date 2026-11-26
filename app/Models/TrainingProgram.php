<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingProgram extends Model
{
    protected $fillable = ['code', 'name', 'institution', 'duration_days', 'lms_program_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
