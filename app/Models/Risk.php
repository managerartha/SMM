<?php

namespace App\Models;

use App\Domain\Risks\RiskLevel;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Risk extends Model
{
    use HasAttachments;

    public const TYPES = ['risiko' => 'Risiko', 'peluang' => 'Peluang'];
    public const TREATMENT_STATUSES = ['rencana' => 'Rencana', 'berjalan' => 'Berjalan', 'selesai' => 'Selesai'];

    protected $fillable = [
        'number', 'type', 'division_id', 'process', 'title', 'description', 'cause', 'consequence', 'likelihood', 'impact',
        'score', 'level', 'existing_controls', 'treatment', 'treatment_pic_id', 'treatment_due_date', 'treatment_status',
        'owner_id', 'review_date', 'status', 'close_note', 'closed_at', 'ncr_id', 'evidence_category_id', 'created_by',
    ];

    protected $casts = ['treatment_due_date' => 'date', 'review_date' => 'date', 'closed_at' => 'datetime'];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function treatmentPic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'treatment_pic_id');
    }

    public function ncr(): BelongsTo
    {
        return $this->belongsTo(Ncr::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(RiskAssessment::class)->orderByDesc('assessed_at')->orderByDesc('id');
    }

    public function levelEnum(): RiskLevel
    {
        return RiskLevel::from($this->level);
    }

    public function isReviewDue(): bool
    {
        return $this->status === 'aktif' && $this->review_date && $this->review_date->lte(now()->addDays(30));
    }
}
