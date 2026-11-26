<?php

namespace App\Http\Controllers;

use App\Domain\Approval\ApprovalMatrix;
use App\Domain\Capa\CapaWorkflow;
use App\Models\Division;
use App\Models\Ncr;
use App\Models\NcrAction;
use App\Models\NcrEffectivenessCheck;
use App\Models\User;
use App\Support\ExcelExport;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NcrController extends Controller
{
    public function __construct(private CapaWorkflow $wf) {}

    public function index(Request $request)
    {
        return view('ncrs.index', [
            'items' => $this->query($request)->paginate(25)->withQueryString(),
            'divisions' => Division::active()->orderBy('code')->get(),
        ]);
    }

    public function export(Request $request)
    {
        $rows = $this->query($request)->with(['actions', 'checks'])->get()->map(fn (Ncr $n) => [
            $n->number, Format::date($n->found_at), Ncr::SOURCES[$n->source] ?? $n->source, $n->complaint?->number, $n->division->code,
            $n->title, $n->description, $n->requirement, $n->severity, $n->pic->name, Format::date($n->target_date), $n->statusLabel(),
            $n->correction, $n->root_cause, $n->actions->pluck('description')->implode(' | '),
            $n->checks->map(fn ($c) => "H{$c->day}: ".($c->result ?? '-'))->implode(', '),
            Format::date($n->closed_at), $n->closed_by_name,
        ]);

        return ExcelExport::download('Register NCR dan CAPA', ['Nomor', 'Ditemukan', 'Sumber', 'Keluhan', 'Divisi', 'Judul', 'Uraian', 'Persyaratan', 'Tingkat', 'PIC', 'Target', 'Status', 'Koreksi', 'Akar masalah', 'Tindakan korektif', 'Efektivitas', 'Ditutup', 'Ditutup oleh'], $rows);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Ncr::class);

        return view('ncrs.create', ['divisions' => Division::active()->orderBy('code')->get(), 'users' => User::active()->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $max = (int) \App\Support\Settings::get('upload.max_mb', 20) * 1024;
        $data = $request->validate([
            'source' => ['required', Rule::in(array_keys(Ncr::SOURCES))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'requirement' => ['nullable', 'string', 'max:255'],
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
            'severity' => ['required', Rule::in(['minor', 'mayor'])],
            'found_at' => ['required', 'date', 'before_or_equal:today'],
            'pic_id' => ['required', 'integer'],
            'target_date' => ['required', 'date'],
            'attachments' => ['array', 'max:5'],
            'attachments.*' => ['file', 'max:'.$max],
        ], [], ['title' => 'judul', 'description' => 'uraian', 'pic_id' => 'penanggung jawab', 'division_id' => 'divisi', 'target_date' => 'target selesai', 'found_at' => 'tanggal ditemukan']);
        $ncr = $this->wf->create($request->user(), $data, $request->file('attachments', []));

        return redirect()->route('ncrs.show', $ncr)->with('status', "{$ncr->number} dibuat.");
    }

    public function show(Request $request, Ncr $ncr, ApprovalMatrix $matrix)
    {
        $this->authorize('view', $ncr);
        $ncr->load(['division', 'pic', 'creator', 'verifier', 'complaint', 'attachments', 'actions.pic', 'actions.attachments', 'checks.checker', 'checks.attachments', 'events']);

        return view('ncrs.show', [
            'ncr' => $ncr,
            'users' => User::active()->orderBy('name')->get(),
            'divisions' => Division::active()->orderBy('code')->get(),
            'closerRole' => config('smm.roles')[$matrix->roleFor('capa_closure')] ?? '–',
        ]);
    }

    public function print(Ncr $ncr)
    {
        $this->authorize('view', $ncr);
        $ncr->load(['division', 'pic', 'creator', 'verifier', 'complaint', 'actions.pic', 'checks.checker', 'events']);

        return view('ncrs.print', ['ncr' => $ncr]);
    }

    public function update(Request $request, Ncr $ncr)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:10000'],
            'requirement' => ['nullable', 'string', 'max:255'], 'severity' => ['required', Rule::in(['minor', 'mayor'])],
            'target_date' => ['required', 'date'], 'pic_id' => ['required', 'integer'], 'division_id' => ['required', 'exists:divisions,id'],
        ]);
        $this->wf->update($request->user(), $ncr, $data);

        return back()->with('status', 'Perubahan disimpan.');
    }

    public function analysis(Request $request, Ncr $ncr)
    {
        $data = $request->validate([
            'correction' => ['nullable', 'string', 'max:10000'],
            'root_cause_method' => ['nullable', Rule::in(array_keys(Ncr::METHODS))],
            'root_cause' => ['nullable', 'string', 'max:10000'],
        ]);
        $this->wf->recordAnalysis($request->user(), $ncr, $data['correction'] ?? null, $data['root_cause_method'] ?? null, $data['root_cause'] ?? null);

        return back()->with('status', 'Koreksi & analisis akar masalah disimpan.');
    }

    public function addAction(Request $request, Ncr $ncr)
    {
        $data = $request->validate([
            'action_description' => ['required', 'string', 'max:5000'], 'action_pic_id' => ['required', 'integer'], 'action_due_date' => ['required', 'date'],
        ], [], ['action_description' => 'uraian tindakan', 'action_pic_id' => 'PIC tindakan', 'action_due_date' => 'tenggat']);
        $this->wf->addAction($request->user(), $ncr, $data['action_description'], (int) $data['action_pic_id'], $data['action_due_date']);

        return back()->with('status', 'Tindakan korektif ditambahkan.');
    }

    public function start(Request $request, Ncr $ncr)
    {
        $this->wf->startActions($request->user(), $ncr);

        return back()->with('status', 'Tindakan korektif dimulai. PIC tindakan sudah diberi tahu.');
    }

    public function completeAction(Request $request, Ncr $ncr, NcrAction $action)
    {
        abort_unless($action->ncr_id === $ncr->id, 404);
        $data = $request->validate(['completion_note' => ['required', 'string', 'max:5000'], 'evidence' => ['array', 'max:5'], 'evidence.*' => ['file']], [], ['completion_note' => 'uraian pelaksanaan']);
        $this->wf->completeAction($request->user(), $action, $data['completion_note'], $request->file('evidence', []));

        return back()->with('status', 'Tindakan ditandai selesai.');
    }

    public function verify(Request $request, Ncr $ncr)
    {
        $data = $request->validate(['verification_note' => ['required', 'string', 'max:5000']], [], ['verification_note' => 'catatan verifikasi']);
        $this->wf->verify($request->user(), $ncr, $data['verification_note']);

        return back()->with('status', 'Pelaksanaan diverifikasi. Pemantauan efektivitas 30/60/90 hari dijadwalkan.');
    }

    public function effectiveness(Request $request, Ncr $ncr, NcrEffectivenessCheck $check)
    {
        abort_unless($check->ncr_id === $ncr->id, 404);
        $data = $request->validate(['result' => ['required', Rule::in(['efektif', 'tidak_efektif'])], 'note' => ['required', 'string', 'max:5000'], 'evidence' => ['array', 'max:5'], 'evidence.*' => ['file']], [], ['note' => 'catatan pemantauan', 'result' => 'hasil']);
        $this->wf->recordEffectiveness($request->user(), $check, $data['result'], $data['note'], $request->file('evidence', []));

        return back()->with('status', $data['result'] === 'efektif' ? 'Hasil pemantauan dicatat.' : 'Dicatat tidak efektif. NCR dibuka ulang untuk analisis akar masalah baru.');
    }

    public function close(Request $request, Ncr $ncr)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['tutup', 'tolak'])], 'note' => ['nullable', 'string', 'max:5000']]);
        if ($data['decision'] === 'tutup') {
            $this->wf->close($request->user(), $ncr, $data['note'] ?? null);

            return back()->with('status', "{$ncr->number} ditutup.");
        }
        $this->wf->rejectClosure($request->user(), $ncr, (string) ($data['note'] ?? ''));

        return back()->with('status', 'Penutupan ditolak; NCR dibuka ulang.');
    }

    public function cancel(Request $request, Ncr $ncr)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->wf->cancel($request->user(), $ncr, $data['note']);

        return back()->with('status', 'NCR dibatalkan.');
    }

    private function query(Request $request)
    {
        $status = $request->query('status', 'aktif');

        return Ncr::with(['division', 'pic', 'complaint'])->visibleTo($request->user())
            ->when($status === 'aktif', fn ($q) => $q->whereNotIn('status', [Ncr::CLOSED, Ncr::CANCELLED]))
            ->when($status === 'lewat', fn ($q) => $q->whereIn('status', [Ncr::OPEN, Ncr::ACTION])->whereDate('target_date', '<', now()->toDateString()))
            ->when(array_key_exists($status, Ncr::STATUSES), fn ($q) => $q->where('status', $status))
            ->when($request->filled('division'), fn ($q) => $q->where('division_id', $request->integer('division')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('title', 'like', $like)->orWhere('description', 'like', $like));
            })
            ->orderByDesc('id');
    }
}
