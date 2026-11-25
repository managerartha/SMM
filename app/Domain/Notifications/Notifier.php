<?php

namespace App\Domain\Notifications;

use App\Models\User;
use App\Notifications\SmmNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Throwable;

class Notifier
{
    /** @param iterable<User>|User $users */
    public function send(iterable|User $users, SmmNotification $notification): void
    {
        $list = $users instanceof User ? collect([$users]) : collect($users);
        $list = $list->filter(fn ($u) => $u instanceof User && $u->canAccess())->unique('id');
        if ($list->isEmpty()) {
            return;
        }
        try {
            Notification::send($list, $notification);
        } catch (Throwable $e) {
            report($e); // Kegagalan email tidak boleh menggagalkan aksi utama.
        }
    }

    /** Pengguna aktif yang punya izin tertentu. */
    public function usersWithPermission(string $permission): Collection
    {
        return User::active()->get()->filter(fn (User $u) => $u->can($permission))->values();
    }
}
