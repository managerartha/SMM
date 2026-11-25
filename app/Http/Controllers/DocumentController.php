<?php

namespace App\Http\Controllers;

use App\Domain\Approval\ApprovalMatrix;
use App\Domain\Documents\DocumentDownloads;
use App\Domain\Documents\DocumentWorkflow;
use App\Models\Division;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\DocumentType;
use App\Models\EvidenceCategory;
use App\Models\User;
use App\Support\ExcelExport;
use App\Support\Format;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function __construct(private DocumentWorkflow $workflow) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Document::class);
        $documents = $this->query($request)->paginate(25)->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'types' => DocumentType::active()->get(),
            'divisions' => Division::active()->orderBy('code')->get(),
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Document::class);
        $user = $request->user();
        $rows = (function () use ($request, $user) {
            foreach ($this->query($request)->get() as $d) {
                if (! $user->can('view', $d)) {
                    continue;
                }
                yield [$d->number, $d->title, $d->type->name, $d->division->code, $d->currentRevision?->label() ?? '–', $d->statusLabel(),
                    Format::date($d->currentRevision?->effective_date), $d->currentRevision?->approved_by_name, Format::date($d->next_review_date),
                    $d->owner->name, $d->access_scope === Document::SCOPE_PUBLIC ? 'Umum' : 'Terbatas', $d->evidenceCategory?->label()];
            }
        })();

        return ExcelExport::download('Daftar Dokumen Terkendali', ['Nomor', 'Judul', 'Jenis', 'Divisi', 'Revisi berlaku', 'Status', 'Tanggal berlaku', 'Disahkan oleh', 'Tinjauan berikutnya', 'Pemilik', 'Cakupan', 'Kategori bukti'], $rows);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Document::class);

        return view('documents.create', $this->formData($request->user()));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Document::class);
        $data = $this->validated($request, true);
        $doc = $this->workflow->create($request->user(), $data, $request->file('pdf'), $request->file('source'));

        return redirect()->route('documents.show', $doc)->with('status', "Dokumen {$doc->number} dibuat sebagai Draf. Ajukan untuk review bila sudah siap.");
    }

    public function show(Request $request, Document $document, ApprovalMatrix $matrix)
    {
        $this->authorize('view', $document);
        $document->load(['type', 'division', 'owner', 'evidenceCategory', 'accessRules.division', 'currentRevision.approver']);
        $user = $request->user();

        $revisions = $document->revisions()->with(['creator', 'reviewer', 'events', 'pdfFile', 'sourceFile'])->get()
            ->filter(fn ($r) => $r->status !== DocumentRevision::CANCELLED || $user->can('manage', $document))
            ->values();
        $open = $revisions->first(fn ($r) => $r->isOpen());

        $reviewers = $open && $open->status === DocumentRevision::DRAFT
            ? User::active()->where('id', '!=', $open->created_by)->get()->filter(fn ($u) => $u->can('dokumen.review'))->sortBy('name')->values()
            : collect();

        return view('documents.show', [
            'document' => $document,
            'revisions' => $revisions,
            'open' => $open,
            'reviewers' => $reviewers,
            'approverRole' => config('smm.roles')[$matrix->roleFor($document->type->approvalSubjectKey())] ?? '–',
            'needsReview' => $open ? ($document->type->review_required || $matrix->canApprove($open->creator, $document->type->approvalSubjectKey())) : true,
        ]);
    }

    public function edit(Request $request, Document $document, DocumentRevision $revision)
    {
        $this->ensureBelongs($document, $revision);
        $this->authorize('manage', $document);
        abort_unless($revision->status === DocumentRevision::DRAFT, 403, 'Hanya draf yang bisa diubah.');

        return view('documents.edit', ['document' => $document, 'revision' => $revision] + $this->formData($request->user()));
    }

    public function update(Request $request, Document $document, DocumentRevision $revision)
    {
        $this->ensureBelongs($document, $revision);
        $data = $this->validated($request, false);
        $this->workflow->updateDraft($request->user(), $revision, $data, $request->file('pdf'), $request->file('source'));

        return redirect()->route('documents.show', $document)->with('status', 'Draf diperbarui.');
    }

    public function submit(Request $request, Document $document, DocumentRevision $revision)
    {
        $this->ensureBelongs($document, $revision);
        $request->validate(['reviewer_id' => ['nullable', 'integer']]);
        $this->workflow->submit($request->user(), $revision, $request->integer('reviewer_id') ?: null);

        return back()->with('status', 'Dokumen diajukan.');
    }

    public function review(Request $request, Document $document, DocumentRevision $revision)
    {
        $this->ensureBelongs($document, $revision);
        $data = $request->validate(['decision' => ['required', Rule::in(['teruskan', 'tolak'])], 'note' => ['nullable', 'string', 'max:2000']]);
        if ($data['decision'] === 'teruskan') {
            $this->workflow->reviewPass($request->user(), $revision, $data['note'] ?? null);
            $msg = 'Review diteruskan ke pengesahan.';
        } else {
            $this->workflow->reviewReject($request->user(), $revision, (string) ($data['note'] ?? ''));
            $msg = 'Dokumen ditolak dan dikembalikan ke Draf.';
        }

        return back()->with('status', $msg);
    }

    public function approve(Request $request, Document $document, DocumentRevision $revision)
    {
        $this->ensureBelongs($document, $revision);
        $data = $request->validate(['decision' => ['required', Rule::in(['sahkan', 'tolak'])], 'note' => ['nullable', 'string', 'max:2000']]);
        if ($data['decision'] === 'sahkan') {
            $this->workflow->approve($request->user(), $revision, $data['note'] ?? null);
            $msg = "{$revision->fullLabel()} disahkan dan kini berlaku.";
        } else {
            $this->workflow->rejectApproval($request->user(), $revision, (string) ($data['note'] ?? ''));
            $msg = 'Dokumen ditolak dan dikembalikan ke Draf.';
        }

        return back()->with('status', $msg);
    }

    public function cancel(Request $request, Document $document, DocumentRevision $revision)
    {
        $this->ensureBelongs($document, $revision);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->workflow->cancel($request->user(), $revision, $data['note']);

        return redirect()->route('documents.show', $document)->with('status', 'Draf dibatalkan.');
    }

    public function download(Request $request, Document $document, DocumentRevision $revision, string $kind, DocumentDownloads $downloads)
    {
        $this->ensureBelongs($document, $revision);

        return $downloads->revision($request->user(), $revision, $kind === 'sumber' ? 'source' : 'pdf');
    }

    public function createRevision(Request $request, Document $document)
    {
        $this->authorize('manage', $document);
        abort_unless($document->current_revision_id && ! $document->openRevision, 403, 'Revisi hanya bisa dibuat dari revisi berlaku, dan tidak boleh ada revisi lain yang sedang dikerjakan.');

        return view('documents.revision', ['document' => $document->load('currentRevision'), 'maxMb' => Settings::get('upload.max_mb')]);
    }

    public function storeRevision(Request $request, Document $document)
    {
        $max = (int) Settings::get('upload.max_mb', 20) * 1024;
        $data = $request->validate([
            'change_reason' => ['required', 'string', 'max:2000'],
            'change_summary' => ['required', 'string', 'max:2000'],
            'pdf' => ['required', 'file', 'max:'.$max],
            'source' => ['nullable', 'file', 'max:'.$max],
        ]);
        $rev = $this->workflow->createRevision($request->user(), $document, $data['change_reason'], $data['change_summary'], $request->file('pdf'), $request->file('source'));

        return redirect()->route('documents.show', $document)->with('status', "Draf {$rev->label()} dibuat.");
    }

    public function archive(Request $request, Document $document)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->workflow->archive($request->user(), $document, $data['note']);

        return redirect()->route('documents.show', $document)->with('status', 'Dokumen diarsipkan.');
    }

    public function restore(Request $request, Document $document)
    {
        $this->workflow->restore($request->user(), $document);

        return redirect()->route('documents.show', $document)->with('status', 'Dokumen dipulihkan dari arsip.');
    }

    // ------------------------------------------------------------------

    private function query(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status', 'aktif');

        return Document::with(['type', 'division', 'owner', 'currentRevision', 'openRevision', 'evidenceCategory'])
            ->visibleTo($user)
            ->when($status === 'arsip', fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->notArchived())
            ->when($status === 'berlaku', fn ($q) => $q->whereNotNull('current_revision_id'))
            ->when($status === 'proses', fn ($q) => $q->whereHas('openRevision'))
            ->when(! $user->can('dokumen.lihat-semua') && ! $user->can('dokumen.kelola'), fn ($q) => $q->whereNotNull('current_revision_id'))
            ->when($request->filled('type'), fn ($q) => $q->where('document_type_id', $request->integer('type')))
            ->when($request->filled('division'), fn ($q) => $q->where('division_id', $request->integer('division')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like)->orWhere('summary', 'like', $like));
            })
            ->orderBy('number');
    }

    private function validated(Request $request, bool $creating): array
    {
        $max = (int) Settings::get('upload.max_mb', 20) * 1024;
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'evidence_category_id' => ['nullable', 'integer', 'exists:evidence_categories,id'],
            'access_scope' => ['required', Rule::in([Document::SCOPE_PUBLIC, Document::SCOPE_RESTRICTED])],
            'access_divisions' => ['array'],
            'access_divisions.*' => ['integer', 'exists:divisions,id'],
            'access_roles' => ['array'],
            'access_roles.*' => [Rule::in(array_keys(config('smm.roles')))],
            'change_summary' => ['nullable', 'string', 'max:2000'],
            'pdf' => [$creating ? 'required' : 'nullable', 'file', 'max:'.$max],
            'source' => ['nullable', 'file', 'max:'.$max],
        ];
        if ($creating) {
            $rules['document_type_id'] = ['required', 'integer', Rule::exists('document_types', 'id')->where('is_active', true)];
            $rules['division_id'] = ['required', 'integer', Rule::exists('divisions', 'id')->where('is_active', true)];
        }
        $data = $request->validate($rules, [], [
            'title' => 'judul', 'pdf' => 'file PDF', 'source' => 'file sumber', 'document_type_id' => 'jenis dokumen', 'division_id' => 'divisi',
        ]);
        $data['access_divisions'] ??= [];
        $data['access_roles'] ??= [];

        return $data;
    }

    private function formData(User $user): array
    {
        return [
            'types' => DocumentType::active()->get(),
            'divisions' => $user->can('dokumen.kelola') ? Division::active()->orderBy('code')->get() : Division::whereKey($user->division_id)->get(),
            'allDivisions' => Division::active()->orderBy('code')->get(),
            'evidence' => EvidenceCategory::orderBy('id')->get(),
            'roles' => config('smm.roles'),
            'maxMb' => Settings::get('upload.max_mb'),
        ];
    }

    private function ensureBelongs(Document $document, DocumentRevision $revision): void
    {
        abort_unless($revision->document_id === $document->id, 404);
    }
}
