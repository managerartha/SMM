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
use App\Support\Format;
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

        // Tahap 3
        $kpiNow = $user->can('kpi.lihat') ? \App\Models\Kpi::with('values')->where('year', (int) now()->format('Y'))->where('is_active', true)->get() : collect();
        $lastPeriod = now()->subMonthNoOverflow()->format('Y-m');
        $kpiStatus = $kpiNow->map(fn ($k) => ['kpi' => $k, 'value' => $k->values->firstWhere('period', $lastPeriod)?->value ?? $k->values->firstWhere('period', now()->format('Y-m'))?->value]);
        $ops = [
            'classes_month' => $user->can('kelas.lihat') ? \App\Models\TrainingClass::whereBetween('start_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->count() : null,
            'survey_avg' => $user->can('survei.lihat') ? round((float) \App\Models\SurveyResponse::where('survey_type', 'kepuasan_peserta')->whereDate('responded_at', '>=', now()->subDays(90))->avg('score'), 2) ?: null : null,
            'personnel_expiring' => $user->can('personel.lihat') ? \App\Models\PersonnelDocument::whereNull('replaced_by_id')->whereNotNull('expires_at')->whereDate('expires_at', '<=', now()->addDays(90))->whereHas('personnel', fn ($q) => $q->where('status', 'aktif'))->count() : null,
            'facilities_due' => $user->can('sarana.lihat') ? \App\Models\Facility::where('status', 'aktif')->where(fn ($q) => $q->whereDate('next_inspection_at', '<=', now()->addDays(14))->orWhere(fn ($c) => $c->where('needs_calibration', true)->whereDate('next_calibration_at', '<=', now()->addDays(14))))->count() : null,
        ];

        // Tahap 4
        $p4 = collect();
        if ($matrix->canApprove($user, 'audit_program_annual', 'mutu.sahkan')) {
            \App\Models\AuditProgram::where('status', 'pengesahan')->get()->each(fn ($p) => $p4->push(['Program Audit '.$p->year, route('audits.programs.show', $p), 'Sahkan', 'purple']));
        }
        if ($matrix->canApprove($user, 'audit_report_process', 'mutu.sahkan')) {
            \App\Models\Audit::where('status', 'pengesahan')->where('auditor_id', '!=', $user->id)->get()->each(fn ($a) => $p4->push([$a->number.' – laporan audit '.$a->process, route('audits.show', $a), 'Sahkan', 'purple']));
        }
        if ($matrix->canApprove($user, 'management_review_minutes', 'mutu.sahkan')) {
            \App\Models\ManagementReview::where('status', 'pengesahan')->get()->each(fn ($m) => $p4->push([$m->number.' – notulen tinjauan manajemen', route('reviews.show', $m), 'Sahkan', 'purple']));
        }
        \App\Models\Innovation::where('status', 'usulan')->get()->filter(fn ($i) => $matrix->canApprove($user, $i->approvalKey(), 'mutu.sahkan'))
            ->each(fn ($i) => $p4->push([$i->number.' – '.$i->title, route('innovations.show', $i), 'Setujui inovasi', 'purple']));
        \App\Models\PerformanceReport::where('status', 'pengesahan')->get()->filter(fn ($r) => $matrix->canApprove($user, $r->approvalKey(), 'mutu.sahkan'))
            ->each(fn ($r) => $p4->push([$r->number.' – laporan kinerja '.$r->periodLabel(), route('reports.show', $r), 'Sahkan', 'purple']));
        \App\Models\Audit::where('auditor_id', $user->id)->whereIn('status', ['rencana', 'pelaksanaan'])->orderBy('planned_date')->get()
            ->each(fn ($a) => $p4->push([$a->number.' – audit '.$a->process.' ('.Format::date($a->planned_date).')', route('audits.show', $a), 'Laksanakan audit', 'blue']));
        \App\Models\ReviewAction::with('review')->where('pic_id', $user->id)->whereNull('completed_at')->orderBy('due_date')->get()
            ->each(fn ($a) => $p4->push([$a->review->number.' – '.\Illuminate\Support\Str::limit($a->description, 50).' ('.Format::date($a->due_date).')', route('reviews.show', $a->review), 'Tindak lanjut TM', $a->due_date->isPast() ? 'red' : 'amber']));

        $backup = $user->can('backup.kelola') ? BackupRun::orderByDesc('id')->first() : null;
        $pendingFiles = $user->can('backup.kelola') ? StoredFile::whereIn('state', [StoredFile::PENDING, StoredFile::FAILED])->count() : null;
        $driveHealth = $user->can('backup.kelola') ? Settings::get('drive.last_health') : null;
        $activity = $user->can('log.lihat') ? ActivityLog::orderByDesc('id')->limit(8)->get() : collect();

        return view('dashboard', compact('toReview', 'toApprove', 'myDrafts', 'reviewDue', 'external', 'stats', 'backup', 'pendingFiles', 'driveHealth', 'activity',
            'myActions', 'toVerify', 'checksDue', 'toClose', 'myComplaints', 'qms', 'kpiStatus', 'lastPeriod', 'ops', 'p4'));
    }
}
