<?php

namespace App\Http\Controllers;

use App\Domain\ExternalDocuments\ExternalDocumentService;
use App\Domain\ExternalDocuments\ValidityStatus;
use App\Models\EvidenceCategory;
use App\Models\ExternalDocument;
use App\Models\ExternalDocumentCategory;
use App\Models\ExternalDocumentVersion;
use App\Support\ExcelExport;
use App\Support\Format;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExternalDocumentController extends Controller
{
    public function __construct(private ExternalDocumentService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', ExternalDocument::class);
        $docs = $this->filtered($request);

        return view('external.index', [
            'docs' => $docs,
            'categories' => ExternalDocumentCategory::active()->get(),
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', ExternalDocument::class);
        $rows = $this->filtered($request)->map(fn ($d) => [
            $d->number, $d->category->name, $d->title, $d->issuer, $d->currentVersion?->original_number,
            Format::date($d->currentVersion?->issued_at), Format::date($d->currentVersion?->expires_at),
            $d->currentVersion?->validity()->label(), $d->currentVersion?->version_no,
            $d->currentVersion?->verified_at ? 'Terverifikasi '.Format::date($d->currentVersion->verified_at) : 'Belum', $d->evidenceCategory?->label(),
        ]);

        return ExcelExport::download('Daftar Legalitas dan Kontrak', ['Nomor internal', 'Kategori', 'Judul', 'Penerbit / pihak kedua', 'Nomor asli', 'Tanggal terbit', 'Tanggal berakhir', 'Status', 'Versi', 'Verifikasi', 'Kategori bukti'], $rows);
    }

    public function create()
    {
        $this->authorize('create', ExternalDocument::class);

        return view('external.create', $this->formData());
    }

    public function store(Request $request)
    {
        $this->authorize('create', ExternalDocument::class);
        $data = $request->validate([
            'external_document_category_id' => ['required', Rule::exists('external_document_categories', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'issuer' => ['required', 'string', 'max:255'],
            'evidence_category_id' => ['nullable', 'integer', 'exists:evidence_categories,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ] + $this->versionRules(), [], $this->attributes());
        $doc = $this->service->create($request->user(), $data, $request->file('file'));

        return redirect()->route('external-documents.show', $doc)->with('status', "Dokumen {$doc->number} disimpan.");
    }

    public function show(ExternalDocument $externalDocument)
    {
        $this->authorize('view', $externalDocument);
        $externalDocument->load(['category', 'evidenceCategory', 'creator', 'versions.file', 'versions.verifier', 'versions.creator', 'currentVersion']);

        return view('external.show', ['doc' => $externalDocument]);
    }

    public function edit(ExternalDocument $externalDocument)
    {
        $this->authorize('update', $externalDocument);

        return view('external.edit', ['doc' => $externalDocument] + $this->formData());
    }

    public function update(Request $request, ExternalDocument $externalDocument)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'issuer' => ['required', 'string', 'max:255'],
            'evidence_category_id' => ['nullable', 'integer', 'exists:evidence_categories,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $this->service->updateInfo($request->user(), $externalDocument, $data);

        return redirect()->route('external-documents.show', $externalDocument)->with('status', 'Informasi diperbarui.');
    }

    public function renewForm(ExternalDocument $externalDocument)
    {
        $this->authorize('update', $externalDocument);

        return view('external.renew', ['doc' => $externalDocument->load('currentVersion')] + $this->formData());
    }

    public function renew(Request $request, ExternalDocument $externalDocument)
    {
        $data = $request->validate($this->versionRules(), [], $this->attributes());
        $v = $this->service->renew($request->user(), $externalDocument, $data, $request->file('file'));

        return redirect()->route('external-documents.show', $externalDocument)->with('status', "Versi {$v->version_no} disimpan. Versi lama tetap tersimpan sebagai riwayat.");
    }

    public function verify(Request $request, ExternalDocument $externalDocument, ExternalDocumentVersion $version)
    {
        abort_unless($version->external_document_id === $externalDocument->id, 404);
        $this->service->verify($request->user(), $version);

        return back()->with('status', 'Keaslian dokumen telah diverifikasi.');
    }

    public function download(Request $request, ExternalDocument $externalDocument, ExternalDocumentVersion $version)
    {
        abort_unless($version->external_document_id === $externalDocument->id, 404);

        return $this->service->download($request->user(), $version);
    }

    public function archive(Request $request, ExternalDocument $externalDocument)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->service->archive($request->user(), $externalDocument, $data['note']);

        return back()->with('status', 'Dokumen diarsipkan.');
    }

    public function restore(Request $request, ExternalDocument $externalDocument)
    {
        $this->service->restore($request->user(), $externalDocument);

        return back()->with('status', 'Dokumen dipulihkan dari arsip.');
    }

    private function filtered(Request $request)
    {
        $status = $request->query('status');
        $user = $request->user();

        return ExternalDocument::with(['category', 'currentVersion', 'evidenceCategory'])
            ->when($request->query('arsip') && $user->can('legalitas.kelola'), fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->notArchived())
            ->when($request->filled('category'), fn ($q) => $q->where('external_document_category_id', $request->integer('category')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like)->orWhere('issuer', 'like', $like)
                    ->orWhereHas('versions', fn ($v) => $v->where('original_number', 'like', $like)));
            })
            ->orderBy('number')->get()
            ->when($status, fn ($c) => $c->filter(fn ($d) => $d->currentVersion && match ($status) {
                'berlaku' => in_array($d->currentVersion->validity(), [ValidityStatus::Valid, ValidityStatus::NoExpiry], true),
                default => $d->currentVersion->validity()->value === $status,
            })->values());
    }

    private function versionRules(): array
    {
        $max = (int) Settings::get('upload.max_mb', 20) * 1024;

        return [
            'original_number' => ['required', 'string', 'max:255'],
            'issued_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'version_notes' => ['nullable', 'string', 'max:2000'],
            'verified' => ['nullable', 'boolean'],
            'file' => ['required', 'file', 'max:'.$max],
        ];
    }

    private function attributes(): array
    {
        return ['original_number' => 'nomor asli', 'issued_at' => 'tanggal terbit', 'expires_at' => 'tanggal berakhir', 'file' => 'berkas', 'issuer' => 'penerbit', 'title' => 'judul'];
    }

    private function formData(): array
    {
        return [
            'categories' => ExternalDocumentCategory::active()->get(),
            'evidence' => EvidenceCategory::orderBy('id')->get(),
            'maxMb' => Settings::get('upload.max_mb'),
        ];
    }
}
