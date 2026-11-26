<?php

namespace App\Http\Controllers;

use App\Domain\Approval\ApprovalMatrix;
use App\Models\ActivityLog;
use App\Models\BackupRun;
use App\Models\Complaint;
use App\Models\Ncr;
use App\Models\NcrAction;
use App\Models\NcrEffectivenessCheck;
use App\Models\Risk;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\DocumentType;
use App\Models\ExternalDocument;
use App\Models\StoredFile;
use App\Support\Settings;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ApprovalMatrix $matrix)
    {
        $user = $request->user();

        // Menunggu tindakan saya
        $toReview = DocumentRevision::with('document.type')->where('status', DocumentRevision::REVIEW)->where('reviewer_id', $user->id)->get();
        $approvableTypes = DocumentType::all()->filter(fn ($t) => $matrix->canApprove($user, $t->approvalSubjectKey()))->pluck('id');
        $toApprove = $approvableTypes->isEmpty() ? collect() : DocumentRevision::with('document.type')
            ->where('status', DocumentRevision::APPROVAL)
            ->whereHas('document', fn ($q) => $q->whereIn('document_type_id', $approvableTypes))
            ->get()->filter(fn ($r) => $user->can('approve', $r));
        $myDrafts = DocumentRevision::with('document')->where('status', DocumentRevision::DRAFT)->where('created_by', $user->id)->get();

        $reviewDays = (int) Settings::get('reminder.review_days', 30);
        $reviewDue = Document::notArchived()->visibleTo($user)->whereNotNull('current_revision_id')
            ->whereDate('next_review_date', '<=', now()->addDays($reviewDays))->orderBy('next_review_date')->limit(10)->get();

        $external = collect();
        if ($user->can('legalitas.lihat') || $user->can('legalitas.kelola')) {
            $external = ExternalDocument::notArchived()->with(['currentVersion', 'category'])->get()
                ->filter(fn ($d) => $d->currentVersion && in_array($d->currentVersion->validity()->value, ['segera', 'kedaluwarsa'], true))
                ->sortBy(fn ($d) => $d->currentVersion->expires_at)->values();
        }

        $stats = [
            'berlaku' => Document::notArchived()->visibleTo($user)->whereNotNull('current_revision_id')->count(),
            'proses' => $user->can('dokumen.lihat-semua') ? DocumentRevision::whereIn('status', DocumentRevision::OPEN_STATUSES)->count() : null,
            'legalitas' => ($user->can('legalitas.lihat') || $user->can('legalitas.kelola')) ? ExternalDocument::notArchived()->count() : null,
        ];

        // Tahap 2
        $myActions = NcrAction::with('ncr')->where('pic_id', $user->id)->where('status', 'rencana')
            ->whereHas('ncr', fn ($q) => $q->where('status', Ncr::ACTION))->orderBy('due_date')->get();
        $toVerify = $user->can('ncr.verifikasi')
            ? Ncr::with('actions')->where('pic_id', '!=', $user->id)->where('status', Ncr::ACTION)->get()
                ->filter(fn ($n) => $n->actions->where('cycle', $n->currentCycle())->where('status', 'rencana')->isEmpty())
            : collect();
        $checksDue = $user->can('ncr.verifikasi')
            ? NcrEffectivenessCheck::with('ncr')->whereNull('result')->whereDate('due_date', '<=', now()->addDays(7))
                ->whereHas('ncr', fn ($q) => $q->where('status', Ncr::MONITORING)->where('pic_id', '!=', $user->id))->orderBy('due_date')->get()
            : collect();
        $toClose = Ncr::where('status', Ncr::CLOSING)->get()->filter(fn ($n) => $user->can('close', $n));
        $myComplaints = Complaint::where('pic_id', $user->id)->whereIn('status', ['baru', 'diproses'])->get();

        $qms = [
            'ncr_open' => Ncr::visibleTo($user)->whereNotIn('status', [Ncr::CLOSED, Ncr::CANCELLED])->count(),
            'ncr_overdue' => Ncr::visibleTo($user)->whereIn('status', [Ncr::OPEN, Ncr::ACTION])->whereDate('target_date', '<', now()->toDateString())->count(),
            'complaints_open' => $user->can('viewAny', Complaint::class) ? Complaint::visibleTo($user)->whereIn('status', ['baru', 'diproses'])->count() : null,
            'complaints_overdue' => $user->can('viewAny', Complaint::class) ? Complaint::visibleTo($user)->whereIn('status', ['baru', 'diproses'])->whereDate('response_due_date', '<', now()->toDateString())->count() : null,
            'risks_high' => $user->can('viewAny', Risk::class) ? Risk::where('status', 'aktif')->where('level', 'tinggi')->count() : null,
        ];

        $backup = $user->can('backup.kelola') ? BackupRun::orderByDesc('id')->first() : null;
        $pendingFiles = $user->can('backup.kelola') ? StoredFile::whereIn('state', [StoredFile::PENDING, StoredFile::FAILED])->count() : null;
        $driveHealth = $user->can('backup.kelola') ? Settings::get('drive.last_health') : null;
        $activity = $user->can('log.lihat') ? ActivityLog::orderByDesc('id')->limit(8)->get() : collect();

        return view('dashboard', compact('toReview', 'toApprove', 'myDrafts', 'reviewDue', 'external', 'stats', 'backup', 'pendingFiles', 'driveHealth', 'activity',
            'myActions', 'toVerify', 'checksDue', 'toClose', 'myComplaints', 'qms'));
    }
}
