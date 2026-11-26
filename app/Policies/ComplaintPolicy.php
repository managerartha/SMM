<?php

namespace App\Policies;

use App\Models\Complaint;
use App\Models\User;

class ComplaintPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('keluhan.catat') || $user->can('keluhan.lihat-semua') || $user->can('keluhan.kelola') || $user->can('keluhan.kelola-divisi');
    }

    public function view(User $user, Complaint $c): bool
    {
        return Complaint::whereKey($c->id)->visibleTo($user)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('keluhan.catat');
    }

    /** Menangani: menugaskan PIC, menanggapi, menyelesaikan. PIC yang ditunjuk juga boleh. */
    public function handle(User $user, Complaint $c): bool
    {
        if (! $c->isOpen()) {
            return false;
        }

        return $user->can('keluhan.kelola')
            || ($user->can('keluhan.kelola-divisi') && $user->division_id && $user->division_id === $c->division_id)
            || $c->pic_id === $user->id;
    }

    /** Menugaskan PIC / membatalkan: hanya penangan (bukan PIC biasa). */
    public function assign(User $user, Complaint $c): bool
    {
        return $c->isOpen() && ($user->can('keluhan.kelola')
            || ($user->can('keluhan.kelola-divisi') && $user->division_id && $user->division_id === $c->division_id));
    }

    public function escalate(User $user, Complaint $c): bool
    {
        return ! $c->ncr_id && $c->status !== 'dibatalkan' && $user->can('ncr.catat') && $this->view($user, $c);
    }

    public function update(User $user, Complaint $c): bool
    {
        return $c->isOpen() && ($this->assign($user, $c) || ($c->created_by === $user->id && $c->status === 'baru'));
    }
}
