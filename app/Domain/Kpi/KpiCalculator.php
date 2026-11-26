<?php

namespace App\Domain\Kpi;

use App\Models\Complaint;
use App\Models\Kpi;
use App\Models\KpiValue;
use App\Models\Ncr;
use App\Models\NcrAction;
use App\Models\SurveyResponse;
use App\Models\TrainingClass;
use Illuminate\Support\Carbon;

/**
 * Perhitungan realisasi KPI otomatis dari data SMM. Nilai manual tidak pernah ditimpa.
 * Bila datanya belum ada (pembagi nol), periode itu dilewati.
 */
class KpiCalculator
{
    public const SOURCES = [
        'manual' => ['Diisi manual', null],
        'survey_avg_peserta' => ['Rata-rata kepuasan peserta (skala 1–5)', 'skala'],
        'survey_satisfied_pct' => ['% peserta puas (skor ≥ 4)', '%'],
        'survey_avg_trainer' => ['Rata-rata evaluasi trainer (1–5)', 'skala'],
        'survey_avg_klien' => ['Rata-rata evaluasi klien (1–5)', 'skala'],
        'complaint_count' => ['Jumlah keluhan masuk', 'keluhan'],
        'complaint_ontime_pct' => ['% keluhan ditanggapi tepat waktu', '%'],
        'ncr_count' => ['Jumlah NCR baru', 'NCR'],
        'capa_ontime_pct' => ['% tindakan korektif selesai tepat waktu', '%'],
        'class_count' => ['Jumlah kelas selesai', 'kelas'],
        'class_pass_rate' => ['% kelulusan peserta', '%'],
        'class_attendance_rate' => ['% kehadiran penuh peserta', '%'],
    ];

    public function value(string $source, string $period): ?float
    {
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();
        $survey = fn (string $type) => SurveyResponse::where('survey_type', $type)->whereBetween('responded_at', [$start->toDateString(), $end->toDateString()]);
        $classes = fn () => TrainingClass::whereBetween('end_date', [$start->toDateString(), $end->toDateString()]);

        return match ($source) {
            'survey_avg_peserta' => $this->avg($survey('kepuasan_peserta')),
            'survey_avg_trainer' => $this->avg($survey('evaluasi_trainer')),
            'survey_avg_klien' => $this->avg($survey('evaluasi_klien')),
            'survey_satisfied_pct' => $this->pct((clone $survey('kepuasan_peserta'))->where('score', '>=', 4)->count(), $survey('kepuasan_peserta')->count()),
            'complaint_count' => (float) Complaint::where('type', 'keluhan')->whereBetween('received_at', [$start->toDateString(), $end->toDateString()])->count(),
            'complaint_ontime_pct' => $this->complaintOnTime($start, $end),
            'ncr_count' => (float) Ncr::whereBetween('found_at', [$start->toDateString(), $end->toDateString()])->where('status', '!=', Ncr::CANCELLED)->count(),
            'capa_ontime_pct' => $this->capaOnTime($start, $end),
            'class_count' => (float) $classes()->count(),
            'class_pass_rate' => $this->pct((int) $classes()->whereNotNull('passed')->sum('passed'), (int) $classes()->whereNotNull('passed')->sum('participants')),
            'class_attendance_rate' => $this->pct((int) $classes()->whereNotNull('attended')->sum('attended'), (int) $classes()->whereNotNull('attended')->sum('participants')),
            default => null,
        };
    }

    /** Hitung & simpan semua KPI otomatis untuk satu periode. Kembalikan jumlah nilai yang disimpan. */
    public function run(string $period): int
    {
        $n = 0;
        Kpi::where('is_active', true)->where('source', '!=', 'manual')->where('year', (int) substr($period, 0, 4))->get()
            ->each(function (Kpi $kpi) use ($period, &$n) {
                $existing = KpiValue::where('kpi_id', $kpi->id)->where('period', $period)->first();
                if ($existing && ! $existing->is_auto) {
                    return; // nilai manual tidak ditimpa
                }
                $v = $this->value($kpi->source, $period);
                if ($v === null) {
                    return;
                }
                KpiValue::updateOrCreate(['kpi_id' => $kpi->id, 'period' => $period], ['value' => round($v, 2), 'is_auto' => true, 'note' => 'Dihitung otomatis', 'entered_by' => null]);
                $n++;
            });

        return $n;
    }

    private function avg($q): ?float
    {
        return $q->count() ? round((float) $q->avg('score'), 2) : null;
    }

    private function pct(int $num, int $den): ?float
    {
        return $den > 0 ? round($num / $den * 100, 2) : null;
    }

    private function complaintOnTime(Carbon $start, Carbon $end): ?float
    {
        $items = Complaint::where('type', 'keluhan')->whereNotNull('response_due_date')
            ->whereBetween('response_due_date', [$start->toDateString(), $end->toDateString()])->where('status', '!=', 'dibatalkan')->get();
        if ($items->isEmpty()) {
            return null;
        }
        $ok = $items->filter(fn ($c) => $c->responded_at && $c->responded_at->copy()->startOfDay()->lte($c->response_due_date))->count();

        return $this->pct($ok, $items->count());
    }

    private function capaOnTime(Carbon $start, Carbon $end): ?float
    {
        $items = NcrAction::whereBetween('due_date', [$start->toDateString(), $end->toDateString()])->where('status', '!=', 'dibatalkan')->get();
        if ($items->isEmpty()) {
            return null;
        }
        $ok = $items->filter(fn ($a) => $a->completed_at && $a->completed_at->copy()->startOfDay()->lte($a->due_date))->count();

        return $this->pct($ok, $items->count());
    }
}
