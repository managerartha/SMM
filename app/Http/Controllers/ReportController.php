<?php

namespace App\Http\Controllers;

use App\Domain\Approval\ApprovalMatrix;
use App\Domain\Reports\PerformanceReportService;
use App\Models\PerformanceReport;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private PerformanceReportService $service) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('laporan.lihat'), 403);

        return view('reports.index', ['items' => PerformanceReport::with('file')->orderByDesc('period')->orderBy('type')->get()]);
    }

    public function store(Request $request)
    {
        $d = $request->validate(['type' => ['required', 'in:bulanan,tahunan'], 'month' => ['nullable', 'required_if:type,bulanan', 'regex:/^\d{4}-\d{2}$/'], 'year' => ['nullable', 'required_if:type,tahunan', 'integer']]);
        $r = $this->service->generate($request->user(), $d['type'], $d['type'] === 'bulanan' ? $d['month'] : (string) $d['year']);

        return redirect()->route('reports.show', $r)->with('status', "{$r->number} disusun dari data SMM terbaru.");
    }

    public function show(Request $request, PerformanceReport $report, ApprovalMatrix $matrix)
    {
        abort_unless($request->user()->can('laporan.lihat'), 403);

        return view('reports.show', ['r' => $report->load('file'), 'canApprove' => $report->status === 'pengesahan' && $matrix->canApprove($request->user(), $report->approvalKey(), 'mutu.sahkan'),
            'approverRole' => config('smm.roles')[$matrix->roleFor($report->approvalKey())] ?? '–']);
    }

    public function regenerate(Request $request, PerformanceReport $report)
    {
        $this->service->generate($request->user(), $report->type, $report->period);

        return back()->with('status', 'Angka diperbarui dari data SMM terbaru.');
    }

    public function analysis(Request $request, PerformanceReport $report)
    {
        $d = $request->validate(['analysis' => ['required', 'string', 'max:20000']]);
        $this->service->saveAnalysis($request->user(), $report, $d['analysis']);

        return back()->with('status', 'Analisis disimpan.');
    }

    public function submit(Request $request, PerformanceReport $report)
    {
        $this->service->submit($request->user(), $report);

        return back()->with('status', 'Laporan diajukan untuk pengesahan.');
    }

    public function decide(Request $request, PerformanceReport $report)
    {
        $d = $request->validate(['decision' => ['required', 'in:sahkan,tolak'], 'note' => ['nullable', 'string', 'max:2000']]);
        $this->service->decide($request->user(), $report, $d['decision'] === 'sahkan', $d['note'] ?? null);

        return back()->with('status', $d['decision'] === 'sahkan' ? 'Laporan disahkan; PDF dibuat.' : 'Laporan dikembalikan.');
    }
}
