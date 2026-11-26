<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kelas dari LMS (sumber utama). SMM hanya menyimpan rekap, tidak pernah mengubah data LMS. */
class TrainingClass extends Model
{
    protected $fillable = [
        'class_code', 'program_name', 'training_program_id', 'scheme', 'client', 'location', 'start_date', 'end_date', 'trainers',
        'participants', 'attended', 'passed', 'average_score', 'lms_status', 'source', 'synced_at',
    ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'synced_at' => 'datetime', 'average_score' => 'float'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(QualityRecord::class)->orderByDesc('activity_date');
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function attendanceRate(): ?float
    {
        return $this->participants ? round($this->attended / $this->participants * 100, 1) : null;
    }

    public function passRate(): ?float
    {
        return $this->participants && $this->passed !== null ? round($this->passed / $this->participants * 100, 1) : null;
    }
}
