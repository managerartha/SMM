<?php

namespace App\Domain\Approval;

use App\Models\ApprovalRule;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Matriks pengesahan (bisa diubah di Admin → Matriks Pengesahan).
 * Pengguna boleh mengesahkan subjek X bila punya peran pengesah X dan izin pengesahan modulnya.
 */
class ApprovalMatrix
{
    private array $cache = [];

    public function roleFor(string $subjectKey): ?string
    {
        return $this->cache[$subjectKey] ??= ApprovalRule::where('subject_key', $subjectKey)->value('approver_role') ?? '';
    }

    public function canApprove(User $user, string $subjectKey, string $permission = 'dokumen.sahkan'): bool
    {
        $role = $this->roleFor($subjectKey);

        return $role !== '' && $user->canAccess() && $user->can($permission) && $user->hasRole($role);
    }

    /** @return Collection<int, User> */
    public function approvers(string $subjectKey, string $permission = 'dokumen.sahkan'): Collection
    {
        $role = $this->roleFor($subjectKey);
        if ($role === '') {
            return collect();
        }

        return User::active()->role($role)->get()->filter(fn (User $u) => $u->can($permission))->values();
    }
}
