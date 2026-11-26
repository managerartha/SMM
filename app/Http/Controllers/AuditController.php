<?php

namespace App\Http\Controllers;

use App\Domain\Approval\ApprovalMatrix;
use App\Domain\Audits\AuditService;
use App\Models\Audit;
use App\Models\AuditChecklistItem;
use App\Models\AuditFinding;
use App\Models\AuditProgram;
use App\Models\Division;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditController extends Controller
{
    public function __construct(private AuditService $service) {}

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->can('audit.lihat') || $user->can('audit.laksana'), 403);

        return view('audits.index', [
            'programs' => AuditProgram::withCount('audits')->orderByDesc('year')->get(),
            'mine' => Audit::with('division')->where('auditor_id', $user->id)->whereIn('status', ['rencana', 'pelaksanaan'])->orderBy('planned_date')->get(),
            'upcoming' => $user->can('audit.lihat') ? Audit::with(['division', 'auditor'])->whereIn('status', ['rencana', 'pelaksanaan', 'pengesahan'])->orderBy('planned_date')->limit(30)->get() : collect(),
        ]);
    }

    public function storeProgram(Request $request)
    {
        $data = $request->validate(['year' => ['required', 'integer', 'min:2020', 'max:2100', 'unique:audit_programs,year'], 'objective' => ['nullable', 'string'], 'scope' => ['nullable', 'string'], 'criteria' => ['nullable', 'string']]);
        $p = $this->service->saveProgram($request->user(), $data + ['criteria' => $data['criteria'] ?? 'ISO 9001:2015; persyaratan penilaian PJK3; SOP & IK yang berlaku']);

        return redirect()->route('audits.programs.show', $p)->with('status', "Program audit {$p->year} dibuat.");
    }

    public function showProgram(Request $request, AuditProgram $program, ApprovalMatrix $matrix)
    {
        abort_unless($request->user()->can('audit.lihat'), 403);
        $program->load(['audits.division', 'audits.auditor', 'file']);

        return view('audits.program', [
            'p' => $program,
            'divisions' => Division::active()->orderBy('code')->get(),
            'auditors' => User::active()->orderBy('name')->get()->filter(fn ($u) => $u->can('audit.laksana'))->values(),
            'users' => User::active()->orderBy('name')->get(),
            'canApprove' => $matrix->canApprove($request->user(), 'audit_program_annual', 'mutu.sahkan'),
        ]);
    }

    public function updateProgram(Request $request, AuditProgram $program)
    {
        $data = $request->validate(['objective' => ['nullable', 'string'], 'scope' => ['nullable', 'string'], 'criteria' => ['nullable', 'string']]);
        $this->service->saveProgram($request->user(), $data, $program);

        return back()->with('status', 'Program disimpan.');
    }

    public function submitProgram(Request $request, AuditProgram $program)
    {
        $this->service->submitProgram($request->user(), $program);

        return back()->with('status', 'Program diajukan untuk pengesahan.');
    }

    public function decideProgram(Request $request, AuditProgram $program)
    {
        $d = $request->validate(['decision' => ['required', 'in:sahkan,tolak'], 'note' => ['nullable', 'string', 'max:2000']]);
        $this->service->decideProgram($request->user(), $program, $d['decision'] === 'sahkan', $d['note'] ?? null);

        return back()->with('status', $d['decision'] === 'sahkan' ? 'Program audit disahkan.' : 'Program dikembalikan ke draf.');
    }

    public function schedule(Request $request, AuditProgram $program)
    {
        $data = $request->validate([
            'division_id' => ['required', 'integer', 'exists:divisions,id'], 'process' => ['required', 'string', 'max:255'],
            'scope' => ['nullable', 'string'], 'criteria' => ['nullable', 'string'], 'planned_date' => ['required', 'date'],
            'auditor_id' => ['required', 'integer', 'exists:users,id'], 'auditee_id' => ['nullable', 'integer', 'exists:users,id'],
        ], [], ['process' => 'proses', 'auditor_id' => 'auditor', 'planned_date' => 'tanggal']);
        $a = $this->service->schedule($request->user(), $program, $data);

        return back()->with('status', "{$a->number} dijadwalkan.");
    }

    public function show(Request $request, Audit $audit, ApprovalMatrix $matrix)
    {
        $user = $request->user();
        abort_unless($user->can('audit.lihat') || $audit->auditor_id === $user->id, 403);
        $audit->load(['program', 'division', 'auditor', 'auditee', 'items', 'findings.ncr', 'attachments', 'file']);

        return view('audits.show', [
            'a' => $audit,
            'canExecute' => $this->service->canExecute($user, $audit),
            'canApprove' => $audit->status === 'pengesahan' && $matrix->canApprove($user, 'audit_report_process', 'mutu.sahkan') && $audit->auditor_id !== $user->id,
        ]);
    }

    public function checklist(Request $request, Audit $audit)
    {
        $data = $request->validate(['items' => ['array'], 'items.*.result' => ['nullable', Rule::in(array_keys(AuditChecklistItem::RESULTS))], 'items.*.evidence' => ['nullable', 'string', 'max:5000'],
            'new_clause' => ['nullable', 'string', 'max:100'], 'new_question' => ['nullable', 'string', 'max:2000']]);
        $this->service->saveChecklist($request->user(), $audit, $data['items'] ?? [], $data['new_clause'] ?? null, $data['new_question'] ?? null);

        return back()->with('status', 'Checklist disimpan.');
    }

    public function finding(Request $request, Audit $audit)
    {
        $data = $request->validate(['type' => ['required', Rule::in(array_keys(AuditFinding::TYPES))], 'clause' => ['nullable', 'string', 'max:100'], 'description' => ['required', 'string', 'max:5000']], [], ['description' => 'uraian temuan']);
        $f = $this->service->addFinding($request->user(), $audit, $data);

        return back()->with('status', 'Temuan dicatat'.($f->ncr ? " dan otomatis menjadi {$f->ncr->number}." : '.'));
    }

    public function report(Request $request, Audit $audit)
    {
        $data = $request->validate(['conclusion' => ['required', 'string', 'max:10000'], 'attachments' => ['array', 'max:10'], 'attachments.*' => ['file']], [], ['conclusion' => 'kesimpulan']);
        $this->service->submitReport($request->user(), $audit, $data['conclusion'], $request->file('attachments', []));

        return back()->with('status', 'Laporan audit diajukan untuk pengesahan.');
    }

    public function decide(Request $request, Audit $audit)
    {
        $d = $request->validate(['decision' => ['required', 'in:sahkan,tolak'], 'note' => ['nullable', 'string', 'max:2000']]);
        $this->service->decideReport($request->user(), $audit, $d['decision'] === 'sahkan', $d['note'] ?? null);

        return back()->with('status', $d['decision'] === 'sahkan' ? 'Laporan audit disahkan.' : 'Laporan dikembalikan ke auditor.');
    }

    public function cancel(Request $request, Audit $audit)
    {
        $d = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->service->cancel($request->user(), $audit, $d['note']);

        return back()->with('status', 'Audit dibatalkan.');
    }
}
