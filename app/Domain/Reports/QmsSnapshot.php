<?php

namespace App\Domain\Reports;

use App\Domain\Surveys\SurveyService;
use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\Complaint;
use App\Models\Facility;
use App\Models\Innovation;
use App\Models\Kpi;
use App\Models\Ncr;
use App\Models\NcrEffectivenessCheck;
use App\Models\PersonnelDocument;
use App\Models\Risk;
use App\Models\SupplierEvaluation;
use App\Models\TrainingClass;
use Carbon\CarbonInterface;

/** Ringkasan angka SMM untuk satu rentang tanggal (dipakai laporan kinerja & tinjauan manajemen). */
class QmsSnapshot
{
    public function __construct(private SurveyService $surveys) {}

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $f = $from->toDateString();
        $t = $to->toDateString();
        $classes = TrainingClass::whereBetween('end_date', [$f, $t])->get();
        $complaints = Complaint::whereBetween('received_at', [$f, $t])->get();
        $keluhan = $complaints->where('type', 'keluhan');
        $ncrs = Ncr::whereBetween('found_at', [$f, $t])->where('status', '!=', Ncr::CANCELLED)->get();
        $checks = NcrEffectivenessCheck::whereNotNull('result')->whereBetween('checked_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->get();
        $years = range((int) $from->format('Y'), (int) $to->format('Y'));
        $months = [];
        for ($d = $from->copy()->startOfMonth(); $d->lte($to); $d->addMonth()) {
            $months[] = $d->format('Y-m');
        }

        $kpis = Kpi::with('values')->whereIn('year', $years)->where('is_active', true)->orderBy('code')->get()->map(function (Kpi $k) use ($months) {
            $vals = $k->values->whereIn('period', $months);
            $avg = $vals->count() ? round($vals->avg('value'), 2) : null;
            $met = $vals->filter(fn ($v) => $k->achieved($v->value))->count();

            return ['code' => $k->code, 'name' => $k->name, 'target' => $k->targetLabel(), 'unit' => $k->unit,
                'values' => $vals->mapWithKeys(fn ($v) => [$v->period => $v->value])->all(), 'average' => $avg,
                'achieved' => $avg !== null ? $k->achieved($avg) : null, 'months_met' => $met, 'months' => $vals->count()];
        })->values()->all();

        $participants = (int) $classes->sum('participants');

        return [
            'period' => ['from' => $f, 'to' => $t],
            'classes' => [
                'count' => $classes->count(),
                'participants' => $participants,
                'attendance_pct' => $participants ? round($classes->sum('attended') / $participants * 100, 1) : null,
                'pass_pct' => $participants ? round($classes->sum('passed') / $participants * 100, 1) : null,
                'by_program' => $classes->groupBy('program_name')->map->count()->sortDesc()->all(),
            ],
            'surveys' => $this->surveys->summary($f, $t),
            'complaints' => [
                'keluhan' => $keluhan->count(), 'feedback' => $complaints->where('type', 'feedback')->count(), 'saran' => $complaints->where('type', 'saran')->count(),
                'open' => $complaints->whereIn('status', ['baru', 'diproses'])->count(),
                'ontime_pct' => ($n = $keluhan->whereNotNull('response_due_date')->where('status', '!=', 'dibatalkan')->count())
                    ? round($keluhan->filter(fn ($c) => $c->responded_at && $c->response_due_date && $c->responded_at->copy()->startOfDay()->lte($c->response_due_date))->count() / $n * 100, 1) : null,
                'by_category' => $keluhan->groupBy('category')->map->count()->sortDesc()->all(),
            ],
            'ncr' => [
                'new' => $ncrs->count(), 'major' => $ncrs->where('severity', 'mayor')->count(),
                'closed' => Ncr::whereBetween('closed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
                'open_total' => Ncr::whereNotIn('status', [Ncr::CLOSED, Ncr::CANCELLED])->count(),
                'overdue' => Ncr::whereIn('status', [Ncr::OPEN, Ncr::ACTION])->whereDate('target_date', '<', $t)->count(),
                'by_source' => $ncrs->groupBy('source')->map->count()->all(),
                'effectiveness_checks' => $checks->count(), 'effective' => $checks->where('result', 'efektif')->count(),
            ],
            'kpis' => $kpis,
            'audits' => [
                'done' => Audit::where('status', 'selesai')->whereBetween('executed_at', [$f, $t])->count(),
                'planned' => Audit::whereBetween('planned_date', [$f, $t])->where('status', '!=', 'dibatalkan')->count(),
                'findings' => AuditFinding::whereHas('audit', fn ($q) => $q->whereBetween('executed_at', [$f, $t]))->get()->groupBy('type')->map->count()->all(),
            ],
            'risks' => [
                'active' => Risk::where('status', 'aktif')->count(),
                'high' => Risk::where('status', 'aktif')->where('level', 'tinggi')->count(),
                'opportunities' => Risk::where('status', 'aktif')->where('type', 'peluang')->count(),
            ],
            'suppliers' => [
                'evaluated' => SupplierEvaluation::whereBetween('evaluated_at', [$f, $t])->count(),
                'average' => round((float) SupplierEvaluation::whereBetween('evaluated_at', [$f, $t])->avg('average'), 2) ?: null,
                'replaced' => SupplierEvaluation::whereBetween('evaluated_at', [$f, $t])->where('decision', 'diganti')->count(),
            ],
            'resources' => [
                'personnel_docs_expiring' => PersonnelDocument::whereNull('replaced_by_id')->whereNotNull('expires_at')->whereDate('expires_at', '<=', $to->copy()->addDays(90))->count(),
                'facilities_bad' => Facility::where('status', 'aktif')->whereIn('condition', ['rusak_ringan', 'rusak_berat'])->count(),
            ],
            'innovations' => [
                'proposed' => Innovation::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
                'completed' => Innovation::where('status', 'selesai')->whereBetween('completed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
            ],
        ];
    }
}
