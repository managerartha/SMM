<?php

namespace App\Listeners;

use App\Domain\Activity\ActivityLogger;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthEvents
{
    public function __construct(private ActivityLogger $log) {}

    public function login(Login $e): void
    {
        if ($e->user instanceof User) {
            $e->user->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->saveQuietly();
            $this->log->log('login', 'Login berhasil', $e->user, [], $e->user);
        }
    }

    public function failed(Failed $e): void
    {
        $email = mb_substr((string) ($e->credentials['email'] ?? ''), 0, 150);
        $this->log->log('login_gagal', 'Login gagal untuk '.$email, null, ['email' => $email], $e->user instanceof User ? $e->user : null);
    }

    public function logout(Logout $e): void
    {
        if ($e->user instanceof User) {
            $this->log->log('logout', 'Logout', $e->user, [], $e->user);
        }
    }
}
