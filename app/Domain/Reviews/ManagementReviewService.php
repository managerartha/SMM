<?php

namespace App\Domain\Reviews;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Approval\ApprovalMatrix;
use App\Domain\Files\FileStore;
use App\Domain\Notifications\Notifier;
use App\Domain\Numbering\NumberGenerator;
use App\Domain\Reports\PdfRenderer;
use App\Domain\Reports\QmsSnapshot;
use App\Models\ManagementReview;
use App\Models\ReviewAction;
use App\Models\User;
use App\Notifications\SmmNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Tinjauan manajemen (ISO 9001 9.3): notulen TM/[tahun]/[urut], masukan 9.3.2 bisa diisi otomatis, keluaran & tindak lanjut. */
class ManagementReviewService
{
    public function __construct(
        private NumberGenerator $numbers, private ActivityLogger $log, private QmsSnapshot $snapshot,
        private ApprovalMatrix $matrix, private Notifier $notifier, private PdfRenderer $pdf, private FileStore $files,
    ) {}

    public function create(User $user, array $data): ManagementReview
    {
        abort_unless($user->can('tinjauan.kelola'), 403);
        $date = Carbon::parse($data['meeting_date']);
        $mr = ManagementReview::create($data + [
            'number' => $this->numbers->next('management_review', ['tahun' => $date->format('Y')], $user->id),
            'inputs' => $this->autoInputs(Carbon::parse($data['period_from']), Carbon::parse($data['period_to'])),
            'outputs' => [], 'status' => 'draf', 'created_by' => $user->id,
        ]);
        $this->log->log('buat', "Membuat notulen tinjauan manajemen {$mr->number}", $mr, [], $user);

        return $mr;
    }

    public function update(User $user, ManagementReview $mr, array $data): void
    {
        abort_unless($user->can('tinjauan.kelola') && $mr->status === 'draf', 403);
        $mr->update($data);
        $this->log->log('ubah', "Mengubah notulen {$mr->number}", $mr, [], $user);
    }

    public function refreshInputs(User $user, ManagementReview $mr): void
    {
        abort_unless($user->can('tinjauan.kelola') && $mr->status === 'draf', 403);
        $mr->update(['inputs' => $this->autoInputs($mr->period_from, $mr->period_to)]);
    }

    public function addAction(User $user, ManagementReview $mr, array $data): ReviewAction
    {
        abort_unless($user->can('tinjauan.kelola') && $mr->status === 'draf', 403);
        $a = $mr->actions()->create($data + ['status' => 'terbuka']);

        return $a;
    }

    public function completeAction(User $user, ReviewAction $a, string $result): void
    {
        abort_unless($a->pic_id === $user->id || $user->can('tinjauan.kelola'), 403);
        if (trim($result) === '') {
            throw ValidationException::withMessages(['result' => 'Uraikan hasil tindak lanjut.']);
        }
        $a->update(['status' => 'selesai', 'result' => $result, 'completed_at' => now()]);
        $this->log->log('ubah', "Menyelesaikan tindak lanjut TM {$a->review->number}", $a->review, [], $user);
    }

    public function submit(User $user, ManagementReview $mr): void
    {
        abort_unless($user->can('tinjauan.kelola') && $mr->status === 'draf', 403);
        if (trim((string) ($mr->outputs['kesimpulan'] ?? '')) === '') {
            throw ValidationException::withMessages(['outputs' => 'Isi kesimpulan tinjauan sebelum diajukan.']);
        }
        $mr->update(['status' => 'pengesahan']);
        $this->log->log('ajukan', "Mengajukan notulen {$mr->number}", $mr, [], $user);
        $this->notifier->send($this->matrix->approvers('management_review_minutes', 'mutu.sahkan'), new SmmNotification("Mohon pengesahan notulen {$mr->number}", ['Notulen tinjauan manajemen menunggu pengesahan.'], 'Buka', route('reviews.show', $mr), 'approval'));
    }

    public function decide(User $user, ManagementReview $mr, bool $approve, ?string $note): void
    {
        abort_unless($mr->status === 'pengesahan' && $this->matrix->canApprove($user, 'management_review_minutes', 'mutu.sahkan'), 403);
        if (! $approve) {
            if (trim((string) $note) === '') {
                throw ValidationException::withMessages(['note' => 'Catatan wajib diisi.']);
            }
            $mr->update(['status' => 'draf', 'approval_note' => $note]);

            return;
        }
        DB::transaction(function () use ($user, $mr, $note) {
            $mr->update(['status' => 'disahkan'] + $mr->recordApproval($user, $note));
            $path = $this->pdf->render('pdf.management-review', ['m' => $mr->fresh(['actions.pic'])]);
            $file = $this->files->storeGenerated($path, 'pdf', 'application/pdf', 'Tinjauan Manajemen/'.$mr->meeting_date->format('Y'), 'Notulen '.str_replace('/', '-', $mr->number), 'management_review', $mr, $user);
            @unlink($path);
            $mr->update(['file_id' => $file->id]);
            $this->log->log('setujui', "Mengesahkan notulen {$mr->number}", $mr, [], $user);
        });
        foreach ($mr->actions()->with('pic')->get() as $a) {
            $this->notifier->send($a->pic, new SmmNotification("Tindak lanjut tinjauan manajemen {$mr->number}", [$a->description, 'Tenggat: '.$a->due_date->format('d-m-Y').'.'], 'Buka', route('reviews.show', $mr), 'warning'));
        }
    }

    /** Ringkasan otomatis dari data SMM untuk masukan 9.3.2 (bisa diedit sebelum diajukan). */
    public function autoInputs(Carbon $from, Carbon $to): array
    {
        $s = $this->snapshot->build($from, $to);
        $prev = ManagementReview::where('status', 'disahkan')->latest('meeting_date')->first();
        $prevActions = $prev ? $prev->actions : collect();
        $sv = $s['surveys'];
        $kpiLines = collect($s['kpis'])->map(fn ($k) => "- {$k['code']} {$k['name']}: rata-rata ".($k['average'] ?? '–')." (target {$k['target']}) – ".($k['achieved'] === null ? 'belum ada data' : ($k['achieved'] ? 'TERCAPAI' : 'TIDAK TERCAPAI'))." ({$k['months_met']}/{$k['months']} bulan tercapai)")->implode("\n");

        return [
            'tindak_lanjut_sebelumnya' => $prev ? "Dari {$prev->number}: {$prevActions->where('status', 'selesai')->count()} dari {$prevActions->count()} tindak lanjut selesai.".($prevActions->where('status', 'terbuka')->isNotEmpty() ? "\nBelum selesai:\n".$prevActions->where('status', 'terbuka')->map(fn ($a) => '- '.$a->description)->implode("\n") : '') : 'Tinjauan manajemen pertama.',
            'isu_internal_eksternal' => '',
            'kepuasan_pelanggan' => "Kepuasan peserta: ".($sv['kepuasan_peserta']['average'] ?? '–')."/5 ({$sv['kepuasan_peserta']['count']} respons, ".($sv['kepuasan_peserta']['satisfied_pct'] ?? '–')."% puas).\nEvaluasi klien: ".($sv['evaluasi_klien']['average'] ?? '–')."/5 ({$sv['evaluasi_klien']['count']} respons).\nKeluhan: {$s['complaints']['keluhan']}, feedback/apresiasi: {$s['complaints']['feedback']}, saran: {$s['complaints']['saran']}. Ditanggapi tepat waktu: ".($s['complaints']['ontime_pct'] ?? '–').'%.',
            'sasaran_mutu' => $kpiLines ?: 'Belum ada KPI.',
            'kinerja_proses' => "Kelas selesai: {$s['classes']['count']} ({$s['classes']['participants']} peserta). Kehadiran penuh ".($s['classes']['attendance_pct'] ?? '–').'%, kelulusan '.($s['classes']['pass_pct'] ?? '–').'%.',
            'ketidaksesuaian_capa' => "NCR baru: {$s['ncr']['new']} (mayor {$s['ncr']['major']}), ditutup: {$s['ncr']['closed']}, masih terbuka: {$s['ncr']['open_total']}, lewat target: {$s['ncr']['overdue']}.\nPemantauan efektivitas: {$s['ncr']['effective']} efektif dari {$s['ncr']['effectiveness_checks']} pemeriksaan.",
            'pemantauan' => 'Evaluasi trainer: '.($sv['evaluasi_trainer']['average'] ?? '–')."/5 ({$sv['evaluasi_trainer']['count']} respons).",
            'hasil_audit' => "Audit selesai: {$s['audits']['done']} dari {$s['audits']['planned']} terjadwal. Temuan: ".(collect($s['audits']['findings'])->map(fn ($n, $k) => "{$k} {$n}")->implode(', ')?:'belum ada').'.',
            'kinerja_pemasok' => "Evaluasi pemasok: {$s['suppliers']['evaluated']} (rata-rata ".($s['suppliers']['average'] ?? '–')."), diganti: {$s['suppliers']['replaced']}.",
            'sumber_daya' => "Berkas personel akan/sudah berakhir (≤ 90 hari): {$s['resources']['personnel_docs_expiring']}. Sarana rusak: {$s['resources']['facilities_bad']}.",
            'risiko_peluang' => "Risiko/peluang aktif: {$s['risks']['active']} (level tinggi {$s['risks']['high']}, peluang {$s['risks']['opportunities']}).",
            'peluang_perbaikan' => "Program inovasi diusulkan: {$s['innovations']['proposed']}, selesai: {$s['innovations']['completed']}.",
        ];
    }
}
