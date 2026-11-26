<?php

namespace App\Domain\Surveys;

use App\Domain\Complaints\ComplaintService;
use App\Models\Complaint;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SurveyService
{
    public function __construct(private ComplaintService $complaints) {}

    /** Ringkasan per jenis survei dalam rentang tanggal. */
    public function summary(?string $from = null, ?string $to = null, ?int $classId = null): array
    {
        $q = fn () => SurveyResponse::query()
            ->when($from, fn (Builder $x) => $x->whereDate('responded_at', '>=', $from))
            ->when($to, fn (Builder $x) => $x->whereDate('responded_at', '<=', $to))
            ->when($classId, fn (Builder $x) => $x->where('training_class_id', $classId));
        $out = [];
        foreach (SurveyResponse::TYPES as $type => $label) {
            $rows = $q()->where('survey_type', $type);
            $count = (clone $rows)->count();
            $out[$type] = [
                'label' => $label,
                'count' => $count,
                'average' => $count ? round((float) (clone $rows)->avg('score'), 2) : null,
                'satisfied_pct' => $count ? round((clone $rows)->where('score', '>=', 4)->count() / $count * 100, 1) : null,
                'negative' => (clone $rows)->where('is_negative', true)->count(),
                'aspects' => $this->aspectAverages((clone $rows)->whereNotNull('aspects')->pluck('aspects')),
            ];
        }

        return $out;
    }

    private function aspectAverages($list): array
    {
        $sum = [];
        $n = [];
        foreach ($list as $aspects) {
            foreach ((array) $aspects as $k => $v) {
                $sum[$k] = ($sum[$k] ?? 0) + $v;
                $n[$k] = ($n[$k] ?? 0) + 1;
            }
        }
        $avg = [];
        foreach ($sum as $k => $s) {
            $avg[$k] = round($s / $n[$k], 2);
        }
        arsort($avg);

        return $avg;
    }

    /** Jawaban survei negatif dijadikan keluhan (sumber: survei). */
    public function toComplaint(User $user, SurveyResponse $r): Complaint
    {
        if ($r->complaint_id) {
            throw ValidationException::withMessages(['survey' => 'Respons ini sudah dijadikan keluhan.']);
        }

        return DB::transaction(function () use ($user, $r) {
            $label = SurveyResponse::TYPES[$r->survey_type] ?? $r->survey_type;
            $c = $this->complaints->create($user, [
                'type' => 'keluhan',
                'source' => 'survei',
                'channel' => 'lms',
                'received_at' => $r->responded_at->toDateString(),
                'class_code' => $r->class_code,
                'reporter_org' => $r->survey_type === 'evaluasi_klien' ? ($r->trainingClass?->client) : null,
                'category' => $r->survey_type === 'evaluasi_trainer' ? 'trainer' : null,
                'subject' => "Survei {$label} negatif (skor {$r->score})".($r->class_code ? " – {$r->class_code}" : ''),
                'description' => ($r->comment ?: 'Tanpa komentar.').($r->subject ? "\nYang dinilai: {$r->subject}" : ''),
                'severity' => $r->score <= 1.5 ? 'tinggi' : 'sedang',
            ]);
            $r->update(['complaint_id' => $c->id]);

            return $c;
        });
    }
}
