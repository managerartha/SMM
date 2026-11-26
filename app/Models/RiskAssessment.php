<?php

namespace App\Models;

use App\Domain\Risks\RiskLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskAssessment extends Model
{
    public $timestamps = false;

    protected $fillable = ['risk_id', 'likelihood', 'impact', 'score', 'level', 'note', 'assessed_by', 'assessed_at'];

    protected $casts = ['assessed_at' => 'datetime'];

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public function levelEnum(): RiskLevel
    {
        return RiskLevel::from($this->level);
    }
}
