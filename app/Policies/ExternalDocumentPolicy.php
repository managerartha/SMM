<?php

namespace App\Policies;

use App\Models\ExternalDocument;
use App\Models\User;

class ExternalDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('legalitas.lihat') || $user->can('legalitas.kelola');
    }

    public function view(User $user, ExternalDocument $doc): bool
    {
        return $this->viewAny($user) && (! $doc->isArchived() || $user->can('legalitas.kelola'));
    }

    public function create(User $user): bool
    {
        return $user->can('legalitas.kelola');
    }

    public function update(User $user, ExternalDocument $doc): bool
    {
        return $user->can('legalitas.kelola') && ! $doc->isArchived();
    }

    public function verify(User $user, ExternalDocument $doc): bool
    {
        return $user->can('legalitas.verifikasi') && ! $doc->isArchived();
    }

    public function archive(User $user, ExternalDocument $doc): bool
    {
        return $user->can('legalitas.kelola');
    }
}
