<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Respons survei dari LMS – tanpa data pribadi peserta (hanya skor, aspek, komentar). */
class SurveyResponse extends Model
{
    public const TYPES = ['kepuasan_peserta' => 'Kepuasan peserta', 'evaluasi_trainer' => 'Evaluasi trainer', 'evaluasi_klien' => 'Evaluasi klien perusahaan'];

    protected $fillable = ['training_class_id', 'class_code', 'survey_type', 'responded_at', 'score', 'aspects', 'comment', 'subject', 'is_negative', 'complaint_id', 'external_id', 'lms_import_id'];

    protected $casts = ['responded_at' => 'date', 'aspects' => 'array', 'is_negative' => 'boolean', 'score' => 'float'];

    public function trainingClass(): BelongsTo
    {
        return $this->belongsTo(TrainingClass::class);
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }
}
