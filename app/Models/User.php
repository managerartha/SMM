<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name', 'email', 'password', 'position', 'division_id', 'is_active', 'access_expires_at',
    ];

    protected $attributes = ['is_active' => true];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'access_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /** Akun bisa dipakai: aktif dan belum melewati masa berlaku (auditor). */
    public function canAccess(): bool
    {
        return $this->is_active && (! $this->access_expires_at || $this->access_expires_at->isFuture());
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_secret) && ! is_null($this->two_factor_confirmed_at);
    }

    public function roleLabels(): string
    {
        $labels = config('smm.roles');

        return $this->getRoleNames()->map(fn ($r) => $labels[$r] ?? $r)->implode(', ');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('access_expires_at')->orWhere('access_expires_at', '>', now()));
    }
}
