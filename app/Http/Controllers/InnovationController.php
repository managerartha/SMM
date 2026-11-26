<?php

namespace App\Http\Controllers;

use App\Domain\Approval\ApprovalMatrix;
use App\Domain\Innovations\InnovationService;
use App\Models\Division;
use App\Models\Innovation;
use App\Models\User;
use App\Support\ExcelExport;
use App\Support\Format;
use Illuminate\Http\Request;

class InnovationController extends Controller
{
    public function __construct(private InnovationService $service) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('inovasi.lihat'), 403);

        return view('innovations.index', ['items' => Innovation::with(['proposer', 'pic'])->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))->orderByDesc('id')->get()]);
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->can('inovasi.lihat'), 403);
        $rows = Innovation::with(['proposer', 'pic'])->orderBy('id')->get()->map(fn ($i) => [$i->number, $i->title, Innovation::STATUSES[$i->status], $i->proposer->name, $i->pic?->name, $i->budget, $i->indicator, $i->baseline, $i->result_value, $i->approved_by_name, Format::date($i->completed_at)]);

        return ExcelExport::download('Program Inovasi Mutu', ['Nomor', 'Judul', 'Status', 'Pengusul', 'PIC', 'Anggaran', 'Indikator', 'Kondisi awal', 'Hasil', 'Disetujui oleh', 'Selesai'], $rows);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->can('inovasi.usul'), 403);

        return view('innovations.form', ['i' => new Innovation(['budget' => 0, 'division_id' => $request->user()->division_id]), 'users' => User::active()->orderBy('name')->get(), 'divisions' => Division::active()->orderBy('code')->get()]);
    }

    public function store(Request $request)
    {
        $i = $this->service->propose($request->user(), $this->validated($request));

        return redirect()->route('innovations.show', $i)->with('status', "{$i->number} diusulkan.");
    }

    public function show(Request $request, Innovation $innovation, ApprovalMatrix $matrix)
    {
        abort_unless($request->user()->can('inovasi.lihat') || in_array($request->user()->id, [$innovation->proposer_id, $innovation->pic_id], true), 403);
        $innovation->load(['proposer', 'pic', 'division', 'attachments']);

        return view('innovations.show', ['i' => $innovation, 'canDecide' => $innovation->status === 'usulan' && $matrix->canApprove($request->user(), $innovation->approvalKey(), 'mutu.sahkan'),
            'approverRole' => config('smm.roles')[$matrix->roleFor($innovation->approvalKey())] ?? '–']);
    }

    public function edit(Request $request, Innovation $innovation)
    {
        return view('innovations.form', ['i' => $innovation, 'users' => User::active()->orderBy('name')->get(), 'divisions' => Division::active()->orderBy('code')->get()]);
    }

    public function update(Request $request, Innovation $innovation)
    {
        $this->service->update($request->user(), $innovation, $this->validated($request));

        return redirect()->route('innovations.show', $innovation)->with('status', 'Usulan diperbarui.');
    }

    public function decide(Request $request, Innovation $innovation)
    {
        $d = $request->validate(['decision' => ['required', 'in:setuju,tolak'], 'note' => ['nullable', 'string', 'max:2000']]);
        $this->service->decide($request->user(), $innovation, $d['decision'] === 'setuju', $d['note'] ?? null);

        return back()->with('status', $d['decision'] === 'setuju' ? 'Usulan disetujui.' : 'Usulan ditolak.');
    }

    public function start(Request $request, Innovation $innovation)
    {
        $this->service->start($request->user(), $innovation);

        return back()->with('status', 'Pelaksanaan dimulai.');
    }

    public function evidence(Request $request, Innovation $innovation)
    {
        $request->validate(['evidence' => ['required', 'array', 'max:10'], 'evidence.*' => ['file']]);
        $this->service->addEvidence($request->user(), $innovation, $request->file('evidence', []));

        return back()->with('status', 'Bukti ditambahkan.');
    }

    public function complete(Request $request, Innovation $innovation)
    {
        $data = $request->validate(['result' => ['required', 'string', 'max:5000'], 'result_value' => ['nullable', 'string', 'max:255'], 'evaluation' => ['required', 'string', 'max:5000']], [], ['result' => 'hasil', 'evaluation' => 'evaluasi']);
        $this->service->complete($request->user(), $innovation, $data);

        return back()->with('status', 'Program inovasi selesai.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'], 'background' => ['required', 'string', 'max:5000'], 'objective' => ['required', 'string', 'max:5000'],
            'plan' => ['nullable', 'string', 'max:5000'], 'indicator' => ['nullable', 'string', 'max:255'], 'baseline' => ['nullable', 'string', 'max:255'],
            'budget' => ['required', 'integer', 'min:0'], 'division_id' => ['nullable', 'integer', 'exists:divisions,id'], 'pic_id' => ['nullable', 'integer', 'exists:users,id'],
            'start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ], [], ['title' => 'judul', 'background' => 'latar belakang', 'objective' => 'tujuan', 'budget' => 'anggaran']);
    }
}
