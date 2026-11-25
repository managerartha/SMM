<?php

namespace App\Domain\Documents;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Approval\ApprovalMatrix;
use App\Domain\Files\FileStore;
use App\Domain\Notifications\Notifier;
use App\Domain\Numbering\NumberGenerator;
use App\Models\Division;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\DocumentType;
use App\Models\User;
use App\Notifications\SmmNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Alur hidup dokumen terkendali:
 * Draf → Review → Menunggu Pengesahan → Berlaku → (revisi baru disahkan) → Kedaluwarsa.
 * Ditolak di review/pengesahan → kembali ke Draf. Semua cek hak akses dilakukan di sini (server).
 */
class DocumentWorkflow
{
    public function __construct(
        private NumberGenerator $numbers,
        private FileStore $files,
        private ActivityLogger $log,
        private ApprovalMatrix $matrix,
        private Notifier $notifier,
    ) {}

    /**
     * @param array{document_type_id:int,division_id:int,title:string,summary:?string,evidence_category_id:?int,access_scope:string,access_divisions?:array,access_roles?:array,change_summary?:?string} $data
     */
    public function create(User $user, array $data, UploadedFile $pdf, ?UploadedFile $source = null): Document
    {
        Gate::forUser($user)->authorize('create', Document::class);
        if (! $user->can('dokumen.kelola') && (int) $data['division_id'] !== (int) $user->division_id) {
            throw ValidationException::withMessages(['division_id' => 'Anda hanya boleh membuat dokumen untuk divisi Anda.']);
        }

        $type = DocumentType::findOrFail($data['document_type_id']);
        $division = Division::findOrFail($data['division_id']);

        return DB::transaction(function () use ($user, $data, $pdf, $source, $type, $division) {
            $number = $this->numbers->next('document', ['jenis' => $type->code, 'divisi' => $division->code], $user->id);

            $doc = Document::create([
                'number' => $number,
                'document_type_id' => $type->id,
                'division_id' => $division->id,
                'title' => $data['title'],
                'summary' => $data['summary'] ?? null,
                'owner_id' => $user->id,
                'evidence_category_id' => $data['evidence_category_id'] ?? $type->default_evidence_category_id,
                'access_scope' => $data['access_scope'] ?? Document::SCOPE_RESTRICTED,
            ]);
            $this->syncAccess($doc, $data);

            $rev = $doc->revisions()->create([
                'revision_no' => 0,
                'status' => DocumentRevision::DRAFT,
                'change_reason' => 'Dokumen baru',
                'change_summary' => $data['change_summary'] ?? 'Terbit pertama',
                'created_by' => $user->id,
            ]);
            $this->attachFiles($rev, $pdf, $source, $user);
            $this->event($rev, 'dibuat', $user);
            $this->log->log('buat', "Membuat dokumen {$number} – {$doc->title}", $doc, [], $user);

            return $doc;
        });
    }

    public function updateDraft(User $user, DocumentRevision $rev, array $data, ?UploadedFile $pdf = null, ?UploadedFile $source = null): void
    {
        $doc = $rev->document;
        Gate::forUser($user)->authorize('manage', $doc);
        $this->ensureStatus($rev, [DocumentRevision::DRAFT]);

        DB::transaction(function () use ($user, $rev, $doc, $data, $pdf, $source) {
            $docFields = array_intersect_key($data, array_flip(['title', 'summary', 'evidence_category_id', 'access_scope']));
            if ($docFields) {
                $doc->update($docFields);
            }
            if (array_key_exists('access_scope', $data)) {
                $this->syncAccess($doc, $data);
            }
            $rev->update(array_intersect_key($data, array_flip(['change_summary', 'change_reason'])));
            if ($pdf || $source) {
                $this->attachFiles($rev, $pdf, $source, $user);
                $this->event($rev, 'file_diganti', $user);
            }
            $this->log->log('ubah', "Mengubah draf {$rev->fullLabel()}", $doc, [], $user);
        });
    }

    public function submit(User $user, DocumentRevision $rev, ?int $reviewerId): void
    {
        $doc = $rev->document;
        Gate::forUser($user)->authorize('manage', $doc);
        $this->ensureStatus($rev, [DocumentRevision::DRAFT]);
        if (! $rev->pdf_file_id) {
            throw ValidationException::withMessages(['pdf' => 'File PDF wajib diunggah sebelum diajukan.']);
        }

        // Review wajib bila jenis dokumen mewajibkan, atau bila pembuat sendiri adalah pengesah
        // (supaya pengesahan dokumen sendiri selalu didahului review orang lain).
        $needsReview = $doc->type->review_required || $this->matrix->canApprove($rev->creator, $doc->type->approvalSubjectKey());

        DB::transaction(function () use ($user, $rev, $doc, $reviewerId, $needsReview) {
            if ($needsReview) {
                $reviewer = $reviewerId ? User::find($reviewerId) : null;
                if (! $reviewer || ! $reviewer->canAccess() || ! $reviewer->can('dokumen.review') || $reviewer->id === $rev->created_by || $reviewer->id === $user->id) {
                    throw ValidationException::withMessages(['reviewer_id' => 'Pilih pereviu yang berwenang dan bukan pembuat dokumen.']);
                }
                $rev->update(['status' => DocumentRevision::REVIEW, 'reviewer_id' => $reviewer->id, 'submitted_at' => now()]);
                $this->event($rev, 'diajukan', $user, 'Pereviu: '.$reviewer->name);
                $this->log->log('ajukan', "Mengajukan {$rev->fullLabel()} untuk review ke {$reviewer->name}", $doc, [], $user);
                DB::afterCommit(fn () => $this->notifier->send($reviewer, new SmmNotification(
                    "Mohon review: {$rev->fullLabel()}",
                    ["{$user->name} mengajukan dokumen \"{$doc->title}\" ({$rev->fullLabel()}) untuk Anda review."],
                    'Buka dokumen', route('documents.show', $doc), 'review'
                )));
            } else {
                $rev->update(['status' => DocumentRevision::APPROVAL, 'submitted_at' => now()]);
                $this->event($rev, 'diajukan_pengesahan', $user);
                $this->log->log('ajukan', "Mengajukan {$rev->fullLabel()} untuk pengesahan", $doc, [], $user);
                DB::afterCommit(fn () => $this->notifyApprovers($rev));
            }
        });
    }

    public function reviewPass(User $user, DocumentRevision $rev, ?string $note = null): void
    {
        Gate::forUser($user)->authorize('review', $rev);
        DB::transaction(function () use ($user, $rev, $note) {
            $rev->update(['status' => DocumentRevision::APPROVAL, 'reviewed_at' => now()]);
            $this->event($rev, 'diteruskan', $user, $note);
            $this->log->log('teruskan', "Meneruskan review {$rev->fullLabel()} ke pengesahan", $rev->document, [], $user);
            DB::afterCommit(fn () => $this->notifyApprovers($rev));
        });
    }

    public function reviewReject(User $user, DocumentRevision $rev, string $note): void
    {
        Gate::forUser($user)->authorize('review', $rev);
        $this->requireNote($note);
        $this->rejectToDraft($user, $rev, 'ditolak_review', $note);
    }

    public function approve(User $user, DocumentRevision $rev, ?string $note = null): void
    {
        Gate::forUser($user)->authorize('approve', $rev);

        DB::transaction(function () use ($user, $rev, $note) {
            // Kunci baris dokumen supaya dua pengesahan bersamaan tidak bentrok.
            $doc = Document::whereKey($rev->document_id)->lockForUpdate()->first();
            $rev->refresh();
            $this->ensureStatus($rev, [DocumentRevision::APPROVAL]);

            $old = $doc->current_revision_id ? DocumentRevision::find($doc->current_revision_id) : null;
            if ($old) {
                $old->update(['status' => DocumentRevision::EXPIRED, 'expired_at' => now(), 'superseded_by_id' => $rev->id]);
                $this->event($old, 'kedaluwarsa', $user, 'Digantikan '.$rev->label());
            }

            $rev->update([
                'status' => DocumentRevision::EFFECTIVE,
                'approved_by' => $user->id,
                'approved_by_name' => $user->name,
                'approved_by_position' => $user->position,
                'approved_at' => now(),
                'effective_date' => now()->toDateString(),
            ]);
            $doc->update([
                'current_revision_id' => $rev->id,
                'next_review_date' => now()->addMonths($doc->type->review_interval_months ?: 12)->toDateString(),
            ]);
            $this->event($rev, 'disahkan', $user, $note);
            $this->log->log('setujui', "Mengesahkan {$rev->fullLabel()}".($old ? " (menggantikan {$old->label()})" : ''), $doc, [], $user);

            DB::afterCommit(fn () => $this->notifier->send(collect([$rev->creator, $doc->owner]), new SmmNotification(
                "Disahkan: {$rev->fullLabel()}",
                ["Dokumen \"{$doc->title}\" ({$rev->fullLabel()}) telah disahkan oleh {$user->name} dan kini berlaku."],
                'Buka dokumen', route('documents.show', $doc), 'success'
            )));
        });
    }

    public function rejectApproval(User $user, DocumentRevision $rev, string $note): void
    {
        Gate::forUser($user)->authorize('approve', $rev);
        $this->requireNote($note);
        $this->rejectToDraft($user, $rev, 'ditolak_pengesahan', $note);
    }

    public function createRevision(User $user, Document $doc, string $reason, ?string $summary, UploadedFile $pdf, ?UploadedFile $source = null): DocumentRevision
    {
        Gate::forUser($user)->authorize('manage', $doc);
        $this->requireNote($reason, 'change_reason', 'Alasan perubahan wajib diisi.');

        return DB::transaction(function () use ($user, $doc, $reason, $summary, $pdf, $source) {
            $doc = Document::whereKey($doc->id)->lockForUpdate()->first();
            if (! $doc->current_revision_id) {
                throw ValidationException::withMessages(['change_reason' => 'Revisi hanya bisa dibuat dari revisi yang sedang berlaku.']);
            }
            if ($doc->openRevision()->exists()) {
                throw ValidationException::withMessages(['change_reason' => 'Masih ada revisi yang sedang dikerjakan untuk dokumen ini.']);
            }
            $next = (int) DocumentRevision::where('document_id', $doc->id)->max('revision_no') + 1;
            $rev = $doc->revisions()->create([
                'revision_no' => $next,
                'status' => DocumentRevision::DRAFT,
                'change_reason' => $reason,
                'change_summary' => $summary,
                'created_by' => $user->id,
            ]);
            $this->attachFiles($rev, $pdf, $source, $user);
            $this->event($rev, 'dibuat', $user, $reason);
            $this->log->log('buat', "Membuat revisi {$rev->fullLabel()}: {$reason}", $doc, [], $user);

            return $rev;
        });
    }

    public function cancel(User $user, DocumentRevision $rev, string $note): void
    {
        $doc = $rev->document;
        Gate::forUser($user)->authorize('manage', $doc);
        $this->ensureStatus($rev, [DocumentRevision::DRAFT]);
        $this->requireNote($note);

        DB::transaction(function () use ($user, $rev, $doc, $note) {
            $rev->update(['status' => DocumentRevision::CANCELLED]);
            $this->event($rev, 'dibatalkan', $user, $note);
            if (! $doc->current_revision_id) {
                $doc->update(['archived_at' => now(), 'archived_by' => $user->id]);
            }
            $this->log->log('batal', "Membatalkan draf {$rev->fullLabel()}: {$note}", $doc, [], $user);
        });
    }

    public function archive(User $user, Document $doc, string $note): void
    {
        Gate::forUser($user)->authorize('archive', $doc);
        $this->requireNote($note);
        if ($doc->openRevision()->exists()) {
            throw ValidationException::withMessages(['note' => 'Batalkan atau selesaikan revisi yang sedang dikerjakan terlebih dahulu.']);
        }
        $doc->update(['archived_at' => now(), 'archived_by' => $user->id]);
        $this->log->log('arsip', "Mengarsipkan dokumen {$doc->number}: {$note}", $doc, [], $user);
    }

    public function restore(User $user, Document $doc): void
    {
        Gate::forUser($user)->authorize('archive', $doc);
        $doc->update(['archived_at' => null, 'archived_by' => null]);
        $this->log->log('pulihkan', "Memulihkan dokumen {$doc->number} dari arsip", $doc, [], $user);
    }

    // ------------------------------------------------------------------

    private function rejectToDraft(User $user, DocumentRevision $rev, string $action, string $note): void
    {
        DB::transaction(function () use ($user, $rev, $action, $note) {
            $rev->update(['status' => DocumentRevision::DRAFT, 'submitted_at' => null, 'reviewed_at' => null]);
            $this->event($rev, $action, $user, $note);
            $this->log->log('tolak', "Menolak {$rev->fullLabel()}: {$note}", $rev->document, [], $user);
            DB::afterCommit(fn () => $this->notifier->send(collect([$rev->creator, $rev->document->owner]), new SmmNotification(
                "Ditolak: {$rev->fullLabel()}",
                ["{$user->name} menolak dokumen \"{$rev->document->title}\" ({$rev->fullLabel()}).", "Catatan: {$note}", 'Dokumen kembali ke status Draf untuk diperbaiki.'],
                'Buka dokumen', route('documents.show', $rev->document), 'warning'
            )));
        });
    }

    private function notifyApprovers(DocumentRevision $rev): void
    {
        $doc = $rev->document;
        $approvers = $this->matrix->approvers($doc->type->approvalSubjectKey());
        $this->notifier->send($approvers, new SmmNotification(
            "Mohon pengesahan: {$rev->fullLabel()}",
            ["Dokumen \"{$doc->title}\" ({$rev->fullLabel()}) menunggu pengesahan Anda."],
            'Buka dokumen', route('documents.show', $doc), 'approval'
        ));
    }

    private function attachFiles(DocumentRevision $rev, ?UploadedFile $pdf, ?UploadedFile $source, User $user): void
    {
        $doc = $rev->document;
        $folder = 'Dokumen Terkendali/'.$doc->type->code.'/'.$doc->number;
        $base = sprintf('Rev %02d - %s', $rev->revision_no, $doc->title);
        $updates = [];
        if ($pdf) {
            $updates['pdf_file_id'] = $this->files->store($pdf, ['pdf'], $folder, $base, 'revision_pdf', $rev, $user, 'pdf', true)->id;
        }
        if ($source) {
            $updates['source_file_id'] = $this->files->store($source, ['docx', 'xlsx'], $folder, $base.' (sumber)', 'revision_source', $rev, $user, 'source')->id;
        }
        if ($updates) {
            $rev->update($updates);
        }
    }

    private function syncAccess(Document $doc, array $data): void
    {
        $doc->accessRules()->delete();
        if (($data['access_scope'] ?? $doc->access_scope) !== Document::SCOPE_RESTRICTED) {
            return;
        }
        foreach (array_unique(array_filter($data['access_divisions'] ?? [])) as $divId) {
            $doc->accessRules()->create(['division_id' => (int) $divId]);
        }
        $validRoles = array_keys(config('smm.roles'));
        foreach (array_unique(array_filter($data['access_roles'] ?? [])) as $role) {
            if (in_array($role, $validRoles, true)) {
                $doc->accessRules()->create(['role_name' => $role]);
            }
        }
    }

    private function event(DocumentRevision $rev, string $action, User $user, ?string $note = null): void
    {
        $rev->events()->create([
            'action' => $action,
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'actor_position' => $user->position,
            'note' => $note,
        ]);
    }

    private function ensureStatus(DocumentRevision $rev, array $allowed): void
    {
        if (! in_array($rev->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'Aksi tidak bisa dilakukan pada status '.$rev->statusLabel().'.']);
        }
    }

    private function requireNote(?string $note, string $field = 'note', string $msg = 'Catatan wajib diisi.'): void
    {
        if (trim((string) $note) === '') {
            throw ValidationException::withMessages([$field => $msg]);
        }
    }
}
