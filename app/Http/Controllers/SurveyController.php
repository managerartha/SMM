<?php

namespace App\Http\Controllers;

use App\Domain\Surveys\SurveyService;
use App\Models\SurveyResponse;
use App\Support\ExcelExport;
use App\Support\Format;
use Illuminate\Http\Request;

class SurveyController extends Controller
{
    public function index(Request $request, SurveyService $service)
    {
        abort_unless($request->user()->can('survei.lihat'), 403);
        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());

        return view('surveys.index', [
            'summary' => $service->summary($from, $to),
            'from' => $from, 'to' => $to,
            'responses' => $this->query($request, $from, $to)->paginate(50)->withQueryString(),
        ]);
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->can('survei.lihat'), 403);
        $rows = $this->query($request, $request->query('from'), $request->query('to'))->get()->map(fn (SurveyResponse $r) => [
            Format::date($r->responded_at), SurveyResponse::TYPES[$r->survey_type], $r->class_code, $r->subject, $r->score,
            collect($r->aspects ?? [])->map(fn ($v, $k) => "{$k}: {$v}")->implode('; '), $r->comment, $r->is_negative ? 'Ya' : '', $r->complaint?->number,
        ]);

        return ExcelExport::download('Rekap Respons Survei', ['Tanggal', 'Jenis', 'Kode kelas', 'Yang dinilai', 'Skor', 'Aspek', 'Komentar', 'Negatif', 'Keluhan'], $rows);
    }

    public function toComplaint(Request $request, SurveyResponse $response, SurveyService $service)
    {
        abort_unless($request->user()->can('survei.lihat') && $request->user()->can('keluhan.catat'), 403);
        $c = $service->toComplaint($request->user(), $response);

        return redirect()->route('complaints.show', $c)->with('status', "Keluhan {$c->number} dibuat dari respons survei.");
    }

    private function query(Request $request, ?string $from, ?string $to)
    {
        return SurveyResponse::with(['complaint', 'trainingClass'])
            ->when($from, fn ($q) => $q->whereDate('responded_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('responded_at', '<=', $to))
            ->when($request->filled('type'), fn ($q) => $q->where('survey_type', $request->string('type')))
            ->when($request->query('negatif'), fn ($q) => $q->where('is_negative', true))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('class_code', 'like', '%'.$request->string('q').'%')->orWhere('comment', 'like', '%'.$request->string('q').'%')->orWhere('subject', 'like', '%'.$request->string('q').'%')))
            ->orderByDesc('responded_at')->orderByDesc('id');
    }
}
