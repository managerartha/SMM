<?php

namespace App\Policies;

use App\Domain\Approval\ApprovalMatrix;
use App\Models\Ncr;
use App\Models\NcrAction;
use App\Models\User;

class NcrPolicy
{
    public function __construct(private ApprovalMatrix $matrix) {}

    /** Semua pengguna boleh membuka daftar; isinya disaring (staf hanya melihat NCR/tindakan yang ditugaskan). */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ncr $ncr): bool
    {
        return Ncr::whereKey($ncr->id)->visibleTo($user)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('ncr.catat');
    }

    /** Mengisi koreksi, akar masalah, tindakan; PIC NCR juga boleh. */
    public function manage(User $user, Ncr $ncr): bool
    {
        if ($ncr->isClosed()) {
            return false;
        }

        return $user->can('ncr.kelola')
            || ($user->can('ncr.kelola-divisi') && $user->division_id && $user->division_id === $ncr->division_id)
            || $ncr->pic_id === $user->id;
    }

    public function completeAction(User $user, NcrAction $action): bool
    {
        return $action->status === 'rencana' && $action->ncr->status === Ncr::ACTION
            && ($action->pic_id === $user->id || $this->manage($user, $action->ncr));
    }

    /** Verifikasi pelaksanaan & pemantauan efektivitas: oleh MR/GM, bukan PIC NCR (pemisahan tugas). */
    public function verify(User $user, Ncr $ncr): bool
    {
        return $user->can('ncr.verifikasi') && $ncr->pic_id !== $user->id;
    }

    public function close(User $user, Ncr $ncr): bool
    {
        return $ncr->status === Ncr::CLOSING && $this->matrix->canApprove($user, 'capa_closure', 'ncr.tutup');
    }

    public function cancel(User $user, Ncr $ncr): bool
    {
        return $ncr->status === Ncr::OPEN && ($user->can('ncr.kelola')
            || ($user->can('ncr.kelola-divisi') && $user->division_id === $ncr->division_id));
    }
}
