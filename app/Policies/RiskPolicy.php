<?php

namespace App\Policies;

use App\Models\Risk;
use App\Models\User;

class RiskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('risiko.lihat') || $user->can('risiko.kelola') || $user->can('risiko.kelola-divisi');
    }

    public function view(User $user, Risk $risk): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('risiko.kelola') || $user->can('risiko.kelola-divisi');
    }

    public function manage(User $user, Risk $risk): bool
    {
        return $user->can('risiko.kelola')
            || ($user->can('risiko.kelola-divisi') && $user->division_id && $user->division_id === $risk->division_id);
    }
}
