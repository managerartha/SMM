<?php

namespace App\Http\Controllers;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Files\FileStore;
use App\Domain\Personnel\PersonnelService;
use App\Models\Personnel;
use App\Models\PersonnelDocument;
use App\Models\User;
use App\Support\ExcelExport;
use App\Support\Format;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonnelController extends Controller
{
    public function __construct(private PersonnelService $service) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('personel.lihat'), 403);

        return view('personnel.index', ['items' => $this->query($request)->get()]);
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->can('personel.lihat'), 403);
        $rows = [];
        foreach ($this->query($request)->with('activeDocuments')->get() as $p) {
            foreach ($p->activeDocuments->whenEmpty(fn ($c) => $c->push(new PersonnelDocument)) as $d) {
                $rows[] = [$p->number, $p->name, Personnel::TYPES[$p->type], $p->position, $p->is_trainer ? 'Ya' : '', $p->status,
                    PersonnelDocument::CATEGORIES[$d->category] ?? '', $d->title, $d->original_number, Format::date($d->issued_at), Format::date($d->expires_at),
                    $d->exists ? $d->validity()->label() : '', $d->verified_at ? 'Ya' : ''];
            }
        }

        return ExcelExport::download('Daftar Personel dan Berkas', ['Nomor', 'Nama', 'Jenis', 'Jabatan', 'Trainer', 'Status', 'Kategori berkas', 'Judul berkas', 'Nomor asli', 'Terbit', 'Berakhir', 'Masa berlaku', 'Terverifikasi'], $rows);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->can('personel.kelola'), 403);

        return view('personnel.form', ['p' => new Personnel(['type' => 'tenaga_ahli', 'status' => 'aktif']), 'users' => User::active()->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('personel.kelola'), 403);
        $p = $this->service->create($request->user(), $this->validated($request, true));

        return redirect()->route('personnel.show', $p)->with('status', "{$p->number} ditambahkan. Unggah berkasnya di bawah.");
    }

    public function show(Request $request, Personnel $personnel)
    {
        abort_unless($request->user()->can('personel.lihat'), 403);
        $personnel->load(['documents.file', 'documents.verifier', 'user']);

        return view('personnel.show', ['p' => $personnel, 'maxMb' => Settings::get('upload.max_mb')]);
    }

    public function edit(Request $request, Personnel $personnel)
    {
        abort_unless($request->user()->can('personel.kelola'), 403);

        return view('personnel.form', ['p' => $personnel, 'users' => User::active()->orderBy('name')->get()]);
    }

    public function update(Request $request, Personnel $personnel)
    {
        abort_unless($request->user()->can('personel.kelola'), 403);
        $this->service->update($request->user(), $personnel, $this->validated($request, false));

        return redirect()->route('personnel.show', $personnel)->with('status', 'Perubahan disimpan.');
    }

    public function storeDocument(Request $request, Personnel $personnel)
    {
        abort_unless($request->user()->can('personel.kelola'), 403);
        $max = (int) Settings::get('upload.max_mb', 20) * 1024;
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(PersonnelDocument::CATEGORIES))],
            'title' => ['required', 'string', 'max:255'],
            'original_number' => ['nullable', 'string', 'max:255'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'verified' => ['nullable', 'boolean'],
            'replaces' => ['nullable', 'integer'],
            'file' => ['required', 'file', 'max:'.$max],
        ], [], ['title' => 'judul berkas', 'file' => 'berkas', 'expires_at' => 'tanggal berakhir']);
        $replaces = ! empty($data['replaces']) ? $personnel->documents()->whereKey($data['replaces'])->first() : null;
        $this->service->addDocument($request->user(), $personnel, $data, $request->file('file'), $replaces);

        return back()->with('status', 'Berkas diunggah.');
    }

    public function verifyDocument(Request $request, Personnel $personnel, PersonnelDocument $document)
    {
        abort_unless($request->user()->can('personel.kelola') && $document->personnel_id === $personnel->id, 403);
        $this->service->verify($request->user(), $document);

        return back()->with('status', 'Berkas diverifikasi.');
    }

    public function downloadDocument(Request $request, Personnel $personnel, PersonnelDocument $document, FileStore $files, ActivityLogger $log)
    {
        abort_unless($request->user()->can('personel.lihat') && $document->personnel_id === $personnel->id, 403);
        $tmp = $files->toTemp($document->file);
        $log->log('unduh', "Mengunduh berkas {$document->title} ({$personnel->number})", $personnel);

        return response()->download($tmp, $files->cleanName("{$personnel->number} {$personnel->name} - {$document->title}").'.'.$document->file->extension,
            ['Content-Type' => $document->file->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'])->deleteFileAfterSend();
    }

    private function query(Request $request)
    {
        return Personnel::withCount('activeDocuments')->with('activeDocuments')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->query('trainer'), fn ($q) => $q->where('is_trainer', true))
            ->when($request->query('status', 'aktif') !== 'semua', fn ($q) => $q->where('status', $request->query('status', 'aktif')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->string('q').'%')->orWhere('number', 'like', '%'.$request->string('q').'%')->orWhere('position', 'like', '%'.$request->string('q').'%')))
            ->orderBy('number');
    }

    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate(($creating ? ['type' => ['required', Rule::in(array_keys(Personnel::TYPES))]] : []) + [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'is_trainer' => ['nullable', 'boolean'],
            'employment' => ['nullable', Rule::in(array_keys(Personnel::EMPLOYMENT))],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'joined_at' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [], ['name' => 'nama']);
        $data['is_trainer'] = $request->boolean('is_trainer');

        return $data;
    }
}
