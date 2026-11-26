<?php

namespace App\Http\Controllers;

use App\Domain\Risks\RiskLevel;
use App\Domain\Risks\RiskService;
use App\Models\Division;
use App\Models\Risk;
use App\Models\User;
use App\Support\ExcelExport;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RiskController extends Controller
{
    public function __construct(private RiskService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Risk::class);
        $items = $this->query($request)->get();
        // Peta panas: jumlah risiko aktif per sel kemungkinan × dampak (sesuai saringan jenis & divisi).
        $heat = [];
        foreach ($items->where('status', 'aktif') as $r) {
            $heat[$r->likelihood][$r->impact][] = $r->number;
        }

        return view('risks.index', ['items' => $items, 'heat' => $heat, 'divisions' => Division::active()->orderBy('code')->get()]);
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Risk::class);
        $rows = $this->query($request)->get()->map(fn (Risk $r) => [
            $r->number, Risk::TYPES[$r->type], $r->division->code, $r->process, $r->title, $r->cause, $r->consequence,
            $r->likelihood, $r->impact, $r->score, $r->levelEnum()->label(), $r->existing_controls, $r->treatment,
            $r->treatmentPic?->name, Format::date($r->treatment_due_date), Risk::TREATMENT_STATUSES[$r->treatment_status] ?? '',
            $r->owner->name, Format::date($r->review_date), $r->status,
        ]);

        return ExcelExport::download('Register Risiko dan Peluang', ['Nomor', 'Jenis', 'Divisi', 'Proses', 'Judul', 'Penyebab', 'Dampak/manfaat', 'Kemungkinan', 'Dampak', 'Skor', 'Level', 'Pengendalian saat ini', 'Rencana penanganan', 'PIC', 'Tenggat', 'Status penanganan', 'Pemilik', 'Tinjauan berikutnya', 'Status'], $rows);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Risk::class);

        return view('risks.form', ['risk' => new Risk(['type' => 'risiko', 'likelihood' => 3, 'impact' => 3, 'division_id' => $request->user()->division_id, 'owner_id' => $request->user()->id]), 'divisions' => $this->divisionsFor($request->user()), 'users' => User::active()->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Risk::class);
        $risk = $this->service->create($request->user(), $request->validate($this->rules(true), [], $this->attributes()));

        return redirect()->route('risks.show', $risk)->with('status', "{$risk->number} dicatat.");
    }

    public function show(Risk $risk)
    {
        $this->authorize('view', $risk);
        $risk->load(['division', 'owner', 'treatmentPic', 'assessments.assessor']);

        return view('risks.show', ['risk' => $risk]);
    }

    public function edit(Request $request, Risk $risk)
    {
        $this->authorize('manage', $risk);

        return view('risks.form', ['risk' => $risk, 'divisions' => $this->divisionsFor($request->user()), 'users' => User::active()->orderBy('name')->get()]);
    }

    public function update(Request $request, Risk $risk)
    {
        $this->service->update($request->user(), $risk, $request->validate($this->rules(false), [], $this->attributes()));

        return redirect()->route('risks.show', $risk)->with('status', 'Perubahan disimpan.');
    }

    public function reassess(Request $request, Risk $risk)
    {
        $data = $request->validate(['likelihood' => ['required', 'integer', 'between:1,5'], 'impact' => ['required', 'integer', 'between:1,5'], 'note' => ['required', 'string', 'max:2000']]);
        $this->service->reassess($request->user(), $risk, (int) $data['likelihood'], (int) $data['impact'], $data['note']);

        return back()->with('status', 'Penilaian ulang disimpan.');
    }

    public function close(Request $request, Risk $risk)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->service->close($request->user(), $risk, $data['note']);

        return back()->with('status', 'Ditutup.');
    }

    public function reopen(Request $request, Risk $risk)
    {
        $this->service->reopen($request->user(), $risk);

        return back()->with('status', 'Dibuka kembali.');
    }

    private function query(Request $request)
    {
        return Risk::with(['division', 'owner', 'treatmentPic'])
            ->when($request->query('status', 'aktif') !== 'semua', fn ($q) => $q->where('status', $request->query('status', 'aktif')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('level'), fn ($q) => $q->where('level', $request->string('level')))
            ->when($request->filled('division'), fn ($q) => $q->where('division_id', $request->integer('division')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like)->orWhere('process', 'like', $like));
            })
            ->orderByDesc('score')->orderBy('number');
    }

    private function divisionsFor(User $user)
    {
        return $user->can('risiko.kelola') ? Division::active()->orderBy('code')->get() : Division::whereKey($user->division_id)->get();
    }

    private function rules(bool $creating): array
    {
        return ($creating ? [
            'type' => ['required', Rule::in(array_keys(Risk::TYPES))],
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
            'likelihood' => ['required', 'integer', 'between:1,5'],
            'impact' => ['required', 'integer', 'between:1,5'],
        ] : []) + [
            'process' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cause' => ['nullable', 'string', 'max:5000'],
            'consequence' => ['nullable', 'string', 'max:5000'],
            'existing_controls' => ['nullable', 'string', 'max:5000'],
            'treatment' => ['nullable', 'string', 'max:5000'],
            'treatment_pic_id' => ['nullable', 'integer', 'exists:users,id'],
            'treatment_due_date' => ['nullable', 'date'],
            'treatment_status' => ['nullable', Rule::in(array_keys(Risk::TREATMENT_STATUSES))],
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'review_date' => ['nullable', 'date'],
        ];
    }

    private function attributes(): array
    {
        return ['title' => 'judul', 'likelihood' => 'kemungkinan', 'impact' => 'dampak', 'owner_id' => 'pemilik risiko', 'division_id' => 'divisi'];
    }
}
