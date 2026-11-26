<?php

namespace App\Domain\Reports;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Approval\ApprovalMatrix;
use App\Domain\Files\FileStore;
use App\Domain\Notifications\Notifier;
use App\Models\PerformanceReport;
use App\Models\User;
use App\Notifications\SmmNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Laporan Kinerja Layanan: bulanan LKL/[tahun]/[bulan] (disahkan GM), tahunan LKL/[tahun] (disahkan Direktur). */
class PerformanceReportService
{
    public function __construct(private QmsSnapshot $snapshot, private PdfRenderer $pdf, private FileStore $files, private ActivityLogger $log, private ApprovalMatrix $matrix, private Notifier $notifier) {}

    public function generate(User $user, string $type, string $period): PerformanceReport
    {
        abort_unless($user->can('laporan.kelola'), 403);
        [$from, $to, $number] = $this->range($type, $period);
        $existing = PerformanceReport::firstWhere('number', $number);
        if ($existing && $existing->status === 'disahkan') {
            throw ValidationException::withMessages(['period' => "{$number} sudah disahkan dan tidak bisa dibuat ulang."]);
        }
        $data = $this->snapshot->build($from, $to);

        return DB::transaction(function () use ($user, $type, $period, $number, $data, $existing) {
            if ($existing) {
                $existing->update(['data' => $data, 'status' => 'draf']);
                $report = $existing;
            } else {
                $report = PerformanceReport::create(['number' => $number, 'type' => $type, 'period' => $period, 'data' => $data, 'status' => 'draf', 'created_by' => $user->id]);
                DB::table('issued_numbers')->insertOrIgnore(['object_type' => 'performance_report', 'number' => $number, 'scope_key' => 'performance_report', 'sequence_value' => 0, 'issued_by' => $user->id, 'created_at' => now()]);
            }
            $this->log->log('buat', "Menyusun {$number} (data diperbarui)", $report, [], $user);

            return $report;
        });
    }

    public function saveAnalysis(User $user, PerformanceReport $r, string $analysis): void
    {
        abort_unless($user->can('laporan.kelola') && $r->status === 'draf', 403);
        $r->update(['analysis' => $analysis]);
    }

    public function submit(User $user, PerformanceReport $r): void
    {
        abort_unless($user->can('laporan.kelola') && $r->status === 'draf', 403);
        if (trim((string) $r->analysis) === '') {
            throw ValidationException::withMessages(['analysis' => 'Isi analisis & tindak lanjut sebelum diajukan.']);
        }
        $r->update(['status' => 'pengesahan']);
        $this->log->log('ajukan', "Mengajukan {$r->number} untuk pengesahan", $r, [], $user);
        $this->notifier->send($this->matrix->approvers($r->approvalKey(), 'mutu.sahkan'), new SmmNotification("Mohon pengesahan {$r->number}", ["Laporan kinerja layanan {$r->periodLabel()} menunggu pengesahan."], 'Buka', route('reports.show', $r), 'approval'));
    }

    public function decide(User $user, PerformanceReport $r, bool $approve, ?string $note): void
    {
        abort_unless($r->status === 'pengesahan' && $this->matrix->canApprove($user, $r->approvalKey(), 'mutu.sahkan'), 403);
        if (! $approve) {
            if (trim((string) $note) === '') {
                throw ValidationException::withMessages(['note' => 'Catatan wajib diisi.']);
            }
            $r->update(['status' => 'draf', 'approval_note' => $note]);
            $this->log->log('tolak', "Mengembalikan {$r->number}: {$note}", $r, [], $user);

            return;
        }
        DB::transaction(function () use ($user, $r, $note) {
            $r->update(['status' => 'disahkan'] + $r->recordApproval($user, $note));
            $path = $this->pdf->render('pdf.performance-report', ['r' => $r->fresh()]);
            $file = $this->files->storeGenerated($path, 'pdf', 'application/pdf', 'Laporan Kinerja/'.substr($r->period, 0, 4), str_replace('/', '-', $r->number).' '.$r->periodLabel(), 'performance_report', $r, $user);
            @unlink($path);
            $r->update(['file_id' => $file->id]);
            $this->log->log('setujui', "Mengesahkan {$r->number}", $r, [], $user);
        });
    }

    /** @return array{0:\Illuminate\Support\Carbon,1:\Illuminate\Support\Carbon,2:string} */
    public function range(string $type, string $period): array
    {
        if ($type === 'tahunan') {
            if (! preg_match('/^\d{4}$/', $period)) {
                throw ValidationException::withMessages(['period' => 'Periode tahunan: YYYY.']);
            }

            return [Carbon::create((int) $period, 1, 1), Carbon::create((int) $period, 12, 31), "LKL/{$period}"];
        }
        if (! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $period, $m)) {
            throw ValidationException::withMessages(['period' => 'Periode bulanan: YYYY-MM.']);
        }
        $from = Carbon::create((int) $m[1], (int) $m[2], 1);

        return [$from, $from->copy()->endOfMonth(), "LKL/{$m[1]}/{$m[2]}"];
    }
}
