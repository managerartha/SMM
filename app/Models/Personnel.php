<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tenaga Ahli (TA) & Tenaga Teknis (TT), termasuk trainer. */
class Personnel extends Model
{
    protected $table = 'personnel';

    public const TYPES = ['tenaga_ahli' => 'Tenaga Ahli (TA)', 'tenaga_teknis' => 'Tenaga Teknis (TT)'];
    public const EMPLOYMENT = ['tetap' => 'Tetap', 'kontrak' => 'Kontrak', 'freelance' => 'Lepas / freelance'];

    protected $fillable = ['number', 'type', 'name', 'position', 'is_trainer', 'employment', 'phone', 'email', 'joined_at', 'user_id', 'status', 'notes', 'created_by'];

    protected $casts = ['is_trainer' => 'boolean', 'joined_at' => 'date'];

    public function documents(): HasMany
    {
        return $this->hasMany(PersonnelDocument::class)->orderBy('category')->orderByDesc('issued_at');
    }

    public function activeDocuments(): HasMany
    {
        return $this->hasMany(PersonnelDocument::class)->whereNull('replaced_by_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function evidenceCategoryId(): int
    {
        return $this->type === 'tenaga_ahli' ? 3 : 4;
    }
}
