<?php

namespace App\Http\Controllers;

use App\Domain\Records\RecordService;
use App\Models\Division;
use App\Models\Document;
use App\Models\EvidenceCategory;
use App\Models\QualityRecord;
use App\Models\TrainingClass;
use App\Support\ExcelExport;
use App\Support\Format;
use App\Support\Settings;
use Illuminate\Http\Request;

class RecordController extends Controller
{
    public function __construct(private RecordService $service) {}

    public function index(Request $request)
    {
        $this->ensureAccess($request);

        return view('records.index', [
            'items' => $this->query($request)->paginate(30)->withQueryString(),
            'divisions' => Division::active()->orderBy('code')->get(),
            'forms' => $this->forms(),
        ]);
    }

    public function export(Request $request)
    {
        $this->ensureAccess($request);
        $rows = $this->query($request)->get()->map(fn (QualityRecord $r) => [
            $r->number, $r->title, $r->form?->number, $r->trainingClass?->class_code, $r->division->code, Format::date($r->activity_date),
            Format::date($r->retention_until), $r->evidenceCategory?->label(), $r->creator->name, $r->attachments()->count(),
        ]);

        return ExcelExport::download('Daftar Catatan Mutu', ['Nomor', 'Judul', 'Formulir', 'Kode kelas', 'Divisi', 'Tanggal kegiatan', 'Disimpan s.d.', 'Kategori bukti', 'Dicatat oleh', 'Jumlah berkas'], $rows);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->can('catatan.catat'), 403);
        $user = $request->user();

        return view('records.create', [
            'forms' => $this->forms(),
            'classes' => TrainingClass::orderByDesc('start_date')->limit(300)->get(),
            'divisions' => $user->can('catatan.lihat-semua') ? Division::active()->orderBy('code')->get() : Division::whereKey($user->division_id)->get(),
            'evidence' => EvidenceCategory::orderBy('id')->get(),
            'selectedClass' => $request->integer('class') ?: null,
            'retention' => Settings::get('record.retention_years', 5),
        ]);
    }

    public function store(Request $request)
    {
        $max = (int) Settings::get('upload.max_mb', 20) * 1024;
        $data = $request->validate([
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
            'training_class_id' => ['nullable', 'integer', 'exists:training_classes,id'],
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'activity_date' => ['required', 'date', 'before_or_equal:today'],
            'retention_years' => ['nullable', 'integer', 'min:1', 'max:30'],
            'evidence_category_id' => ['nullable', 'integer', 'exists:evidence_categories,id'],
            'attachments' => ['required', 'array', 'min:1', 'max:20'],
            'attachments.*' => ['file', 'max:'.$max],
        ], [], ['title' => 'judul', 'activity_date' => 'tanggal kegiatan', 'attachments' => 'berkas bukti', 'division_id' => 'divisi']);
        $record = $this->service->create($request->user(), $data, $request->file('attachments', []));

        return redirect()->route('records.show', $record)->with('status', "{$record->number} tersimpan.");
    }

    public function show(Request $request, QualityRecord $record)
    {
        $this->ensureView($request, $record);
        $record->load(['form', 'trainingClass', 'division', 'creator', 'attachments', 'evidenceCategory']);

        return view('records.show', ['r' => $record]);
    }

    public function addFiles(Request $request, QualityRecord $record)
    {
        $this->ensureView($request, $record);
        abort_unless($request->user()->can('catatan.catat'), 403);
        $request->validate(['attachments' => ['required', 'array', 'max:20'], 'attachments.*' => ['file']]);
        $this->service->addFiles($request->user(), $record, $request->file('attachments', []));

        return back()->with('status', 'Berkas ditambahkan.');
    }

    public function archive(Request $request, QualityRecord $record)
    {
        abort_unless($request->user()->can('catatan.lihat-semua') && $request->user()->can('catatan.catat'), 403);
        $this->service->archive($request->user(), $record);

        return back()->with('status', 'Catatan diarsipkan.');
    }

    private function query(Request $request)
    {
        $status = $request->query('status', 'aktif');

        return QualityRecord::with(['form', 'trainingClass', 'division', 'creator'])->visibleTo($request->user())
            ->when($status === 'aktif', fn ($q) => $q->whereNull('archived_at'))
            ->when($status === 'lewat', fn ($q) => $q->whereNull('archived_at')->whereDate('retention_until', '<', now()->toDateString()))
            ->when($status === 'arsip', fn ($q) => $q->whereNotNull('archived_at'))
            ->when($request->filled('form'), fn ($q) => $q->where('document_id', $request->integer('form')))
            ->when($request->filled('division'), fn ($q) => $q->where('division_id', $request->integer('division')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like)->orWhere('description', 'like', $like)
                    ->orWhereHas('trainingClass', fn ($c) => $c->where('class_code', 'like', $like)));
            })
            ->orderByDesc('activity_date')->orderByDesc('id');
    }

    private function forms()
    {
        return Document::with('currentRevision')->whereNull('archived_at')->whereNotNull('current_revision_id')
            ->whereHas('type', fn ($t) => $t->where('code', 'F'))->orderBy('number')->get();
    }

    private function ensureAccess(Request $request): void
    {
        abort_unless($request->user()->can('catatan.catat') || $request->user()->can('catatan.lihat-semua'), 403);
    }

    private function ensureView(Request $request, QualityRecord $r): void
    {
        abort_unless(QualityRecord::whereKey($r->id)->visibleTo($request->user())->exists(), 403);
    }
}
