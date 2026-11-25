<?php

namespace App\Policies;

use App\Domain\Approval\ApprovalMatrix;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\User;

/**
 * Semua cek hak akses dokumen ada di sini dan dipanggil di server (controller & layanan),
 * bukan hanya menyembunyikan tombol.
 */
class DocumentPolicy
{
    public function __construct(private ApprovalMatrix $matrix) {}

    public function viewAny(User $user): bool
    {
        return $user->can('dokumen.lihat') || $user->can('dokumen.lihat-semua');
    }

    public function create(User $user): bool
    {
        return $user->can('dokumen.kelola') || $user->can('dokumen.kelola-divisi');
    }

    /** Boleh membuat/mengubah/mengajukan revisi dokumen ini. */
    public function manage(User $user, Document $doc): bool
    {
        if ($doc->isArchived()) {
            return false;
        }

        return $user->can('dokumen.kelola')
            || ($user->can('dokumen.kelola-divisi') && $user->division_id && $user->division_id === $doc->division_id);
    }

    public function view(User $user, Document $doc): bool
    {
        if ($this->manage($user, $doc) || $user->can('dokumen.kelola')) {
            return true;
        }
        if ($user->can('dokumen.lihat-semua')) {
            return true; // termasuk arsip & dokumen yang belum berlaku (tanpa hak unduh draf)
        }
        if ($doc->isArchived()) {
            return false;
        }
        if ($this->isInvolved($user, $doc)) {
            return true;
        }
        if (! $doc->current_revision_id) {
            return false;
        }

        return Document::whereKey($doc->id)->visibleTo($user)->exists() && $user->can('dokumen.lihat');
    }

    public function downloadRevision(User $user, DocumentRevision $rev): bool
    {
        $doc = $rev->document;

        return match ($rev->status) {
            DocumentRevision::EFFECTIVE => $this->view($user, $doc),
            DocumentRevision::EXPIRED => $user->can('dokumen.unduh-kedaluwarsa'),
            DocumentRevision::DRAFT, DocumentRevision::REVIEW, DocumentRevision::APPROVAL => $this->manage($user, $doc)
                || $rev->reviewer_id === $user->id
                || ($rev->status === DocumentRevision::APPROVAL && $this->matrix->canApprove($user, $doc->type->approvalSubjectKey())),
            default => $user->can('dokumen.kelola'),
        };
    }

    public function downloadSource(User $user, DocumentRevision $rev): bool
    {
        return $user->can('dokumen.unduh-sumber') && $this->downloadRevision($user, $rev);
    }

    public function review(User $user, DocumentRevision $rev): bool
    {
        return $rev->status === DocumentRevision::REVIEW && $rev->reviewer_id === $user->id && $user->can('dokumen.review');
    }

    public function approve(User $user, DocumentRevision $rev): bool
    {
        if ($rev->status !== DocumentRevision::APPROVAL) {
            return false;
        }
        if (! $this->matrix->canApprove($user, $rev->document->type->approvalSubjectKey())) {
            return false;
        }
        // Mengesahkan dokumen buatan sendiri hanya boleh bila sudah direviu orang lain.
        if ($rev->created_by === $user->id) {
            return $rev->reviewed_at !== null && $rev->reviewer_id !== null && $rev->reviewer_id !== $user->id;
        }

        return true;
    }

    public function archive(User $user, Document $doc): bool
    {
        return $user->can('dokumen.arsip');
    }

    private function isInvolved(User $user, Document $doc): bool
    {
        $open = $doc->openRevision;
        if (! $open) {
            return false;
        }

        return $open->reviewer_id === $user->id
            || ($open->status === DocumentRevision::APPROVAL && $this->matrix->canApprove($user, $doc->type->approvalSubjectKey()));
    }
}
