<?php

namespace App\Domain\Audits;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Approval\ApprovalMatrix;
use App\Domain\Capa\CapaWorkflow;
use App\Domain\Files\FileStore;
use App\Domain\Notifications\Notifier;
use App\Domain\Numbering\NumberGenerator;
use App\Domain\Reports\PdfRenderer;
use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\AuditProgram;
use App\Models\Division;
use App\Models\User;
use App\Notifications\SmmNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Audit internal (ISO 9001 klausul 9.2):
 * Program audit tahunan (disahkan sesuai matriks "audit_program_annual") → audit per proses AI/[tahun]/[urut]
 * → checklist & temuan (mayor/minor langsung menjadi NCR) → laporan (disahkan sesuai "audit_report_process").
 */
class AuditService
{
    /** Checklist bawaan (bisa ditambah/diubah per audit). */
    public const DEFAULT_CHECKLIST = [
        ['4.4', 'Apakah proses memiliki SOP/IK yang berlaku dan dipahami pelaksananya?'],
        ['7.5', 'Apakah dokumen yang dipakai adalah revisi berlaku (cek nomor revisi di SMM)?'],
        ['7.5.3', 'Apakah catatan mutu proses lengkap, terbaca, dan tersimpan sesuai masa simpan?'],
        ['7.2', 'Apakah personel yang terlibat kompeten (sertifikat/lisensi masih berlaku)?'],
        ['7.1.3', 'Apakah sarana/alat yang dipakai layak dan terpelihara (jadwal pemeriksaan/kalibrasi)?'],
        ['8.5.1', 'Apakah pelaksanaan layanan sesuai rencana (rundown, materi, absensi)?'],
        ['9.1.2', 'Apakah kepuasan pelanggan dipantau dan ditindaklanjuti?'],
        ['10.2', 'Apakah ketidaksesuaian/keluhan sebelumnya sudah ditindaklanjuti dan efektif?'],
        ['6.2', 'Apakah sasaran mutu proses dipantau dan tercapai?'],
        ['6.1', 'Apakah risiko proses sudah diidentifikasi dan dikendalikan?'],
    ];

    public function __construct(
        private NumberGenerator $numbers,
        private ActivityLogger $log,
        private ApprovalMatrix $matrix,
        private Notifier $notifier,
        private FileStore $files,
        private PdfRenderer $pdf,
        private CapaWorkflow $capa,
    ) {}

    public function saveProgram(User $user, array $data, ?AuditProgram $program = null): AuditProgram
    {
        abort_unless($user->can('audit.kelola'), 403);
        if ($program && $program->status === 'disahkan') {
            // Perubahan setelah disahkan → kembali ke draf dan perlu disahkan ulang
            $data['status'] = 'draf';
        }
        $program = $program ? tap($program)->update($data) : AuditProgram::create($data + ['created_by' => $user->id, 'status' => 'draf']);
        $this->log->log('ubah', "Menyimpan program audit {$program->year}", $program, [], $user);

        return $program;
    }

    public function submitProgram(User $user, AuditProgram $p): void
    {
        abort_unless($user->can('audit.kelola'), 403);
        if ($p->audits()->where('status', '!=', 'dibatalkan')->doesntExist()) {
            throw ValidationException::withMessages(['program' => 'Jadwalkan minimal satu audit sebelum diajukan.']);
        }
        $p->update(['status' => 'pengesahan']);
        $this->log->log('ajukan', "Mengajukan program audit {$p->year} untuk pengesahan", $p, [], $user);
        $this->notifyApprovers('audit_program_annual', "Mohon pengesahan program audit {$p->year}", route('audits.programs.show', $p));
    }

    public function decideProgram(User $user, AuditProgram $p, bool $approve, ?string $note): void
    {
        abort_unless($p->status === 'pengesahan' && $this->matrix->canApprove($user, 'audit_program_annual', 'mutu.sahkan'), 403);
        if (! $approve) {
            $this->requireNote($note);
            $p->update(['status' => 'draf', 'approval_note' => $note]);
            $this->log->log('tolak', "Menolak program audit {$p->year}: {$note}", $p, [], $user);

            return;
        }
        DB::transaction(function () use ($user, $p, $note) {
            $p->update(['status' => 'disahkan'] + $p->recordApproval($user, $note));
            $path = $this->pdf->render('pdf.audit-program', ['p' => $p->fresh(['audits.division', 'audits.auditor'])]);
            $file = $this->files->storeGenerated($path, 'pdf', 'application/pdf', 'Audit Internal/'.$p->year, 'Program Audit '.$p->year, 'audit_program', $p, $user);
            @unlink($path);
            $p->update(['file_id' => $file->id]);
            $this->log->log('setujui', "Mengesahkan program audit {$p->year}", $p, [], $user);
        });
    }

    public function schedule(User $user, AuditProgram $p, array $data): Audit
    {
        abort_unless($user->can('audit.kelola'), 403);
        $auditor = User::findOrFail($data['auditor_id']);
        if (! $auditor->canAccess() || ! $auditor->can('audit.laksana')) {
            throw ValidationException::withMessages(['auditor_id' => 'Auditor harus pengguna aktif yang berwenang melaksanakan audit.']);
        }
        if ($auditor->division_id && (int) $auditor->division_id === (int) $data['division_id']) {
            throw ValidationException::withMessages(['auditor_id' => 'Auditor tidak boleh mengaudit divisinya sendiri (independensi, ISO 9001 9.2.2).']);
        }
        $date = Carbon::parse($data['planned_date']);
        if ((int) $date->format('Y') !== $p->year) {
            throw ValidationException::withMessages(['planned_date' => 'Tanggal harus di tahun program ('.$p->year.').']);
        }

        return DB::transaction(function () use ($user, $p, $data, $auditor, $date) {
            $auditeeId = $data['auditee_id'] ?? Division::find($data['division_id'])?->head_user_id;
            $audit = $p->audits()->create([
                'number' => $this->numbers->next('audit', ['tahun' => $date->format('Y')], $user->id),
                'division_id' => $data['division_id'], 'process' => $data['process'], 'scope' => $data['scope'] ?? null,
                'criteria' => $data['criteria'] ?? 'ISO 9001:2015, persyaratan PJK3, SOP terkait',
                'planned_date' => $date->toDateString(), 'auditor_id' => $auditor->id, 'auditee_id' => $auditeeId,
                'status' => 'rencana', 'created_by' => $user->id,
            ]);
            foreach (self::DEFAULT_CHECKLIST as $i => [$clause, $q]) {
                $audit->items()->create(['sort' => $i, 'clause' => $clause, 'question' => $q]);
            }
            if ($p->status === 'disahkan') {
                $p->update(['status' => 'draf']); // jadwal berubah → program perlu disahkan ulang
            }
            $this->log->log('buat', "Menjadwalkan audit {$audit->number} ({$audit->process}) oleh {$auditor->name}", $audit, [], $user);
            DB::afterCommit(fn () => $this->notifier->send(collect([$auditor, $audit->auditee]), new SmmNotification(
                "Jadwal audit internal {$audit->number}",
                ["Audit proses \"{$audit->process}\" dijadwalkan ".$date->format('d-m-Y').'.', "Auditor: {$auditor->name}."],
                'Buka audit', route('audits.show', $audit), 'info'
            )));

            return $audit;
        });
    }

    public function canExecute(User $user, Audit $a): bool
    {
        return in_array($a->status, ['rencana', 'pelaksanaan'], true) && $user->can('audit.laksana') && $a->auditor_id === $user->id;
    }

    public function saveChecklist(User $user, Audit $a, array $items, ?string $newClause, ?string $newQuestion): void
    {
        abort_unless($this->canExecute($user, $a), 403);
        DB::transaction(function () use ($a, $items, $newClause, $newQuestion) {
            foreach ($items as $id => $row) {
                $a->items()->whereKey($id)->update(['result' => $row['result'] ?: null, 'evidence' => $row['evidence'] ?? null]);
            }
            if (trim((string) $newQuestion) !== '') {
                $a->items()->create(['sort' => (int) $a->items()->max('sort') + 1, 'clause' => $newClause, 'question' => $newQuestion]);
            }
            if ($a->status === 'rencana') {
                $a->update(['status' => 'pelaksanaan', 'executed_at' => now()->toDateString()]);
            }
        });
        $this->log->log('ubah', "Mengisi checklist audit {$a->number}", $a, [], $user);
    }

    public function addFinding(User $user, Audit $a, array $data): AuditFinding
    {
        abort_unless($this->canExecute($user, $a), 403);

        return DB::transaction(function () use ($user, $a, $data) {
            $finding = $a->findings()->create($data + ['created_by' => $user->id]);
            if (in_array($data['type'], ['mayor', 'minor'], true)) {
                // Temuan ketidaksesuaian langsung menjadi NCR
                $pic = $a->auditee ?? Division::find($a->division_id)?->head ?? User::role('mr')->first();
                $ncr = $this->capa->create($user, [
                    'source' => 'audit_internal',
                    'title' => "Temuan audit {$a->number}: ".mb_substr($data['description'], 0, 120),
                    'description' => $data['description']."\n\nTemuan audit internal {$a->number} (proses: {$a->process}).",
                    'requirement' => $data['clause'] ? 'ISO 9001 klausul '.$data['clause'] : null,
                    'division_id' => $a->division_id, 'severity' => $data['type'], 'found_at' => now()->toDateString(),
                    'pic_id' => $pic?->id, 'target_date' => now()->addDays($data['type'] === 'mayor' ? 30 : 60)->toDateString(),
                ], [], false);
                $finding->update(['ncr_id' => $ncr->id]);
            }
            if ($a->status === 'rencana') {
                $a->update(['status' => 'pelaksanaan', 'executed_at' => now()->toDateString()]);
            }
            $this->log->log('buat', "Mencatat temuan {$data['type']} pada {$a->number}".($finding->ncr_id ? " → {$finding->ncr->number}" : ''), $a, [], $user);

            return $finding->fresh('ncr');
        });
    }

    /** @param UploadedFile[] $attachments */
    public function submitReport(User $user, Audit $a, string $conclusion, array $attachments = []): void
    {
        abort_unless($this->canExecute($user, $a), 403);
        $this->requireNote($conclusion, 'conclusion', 'Kesimpulan audit wajib diisi.');
        if ($a->items()->whereNull('result')->exists()) {
            throw ValidationException::withMessages(['conclusion' => 'Lengkapi hasil semua butir checklist (boleh "Tidak berlaku").']);
        }
        DB::transaction(function () use ($user, $a, $conclusion, $attachments) {
            $a->update(['conclusion' => $conclusion, 'status' => 'pengesahan', 'executed_at' => $a->executed_at ?? now()->toDateString()]);
            foreach (array_values(array_filter($attachments)) as $i => $f) {
                $this->files->store($f, ['pdf', 'jpg', 'png', 'docx', 'xlsx'], $a->folderPath(), 'Bukti audit '.($i + 1), 'audit_evidence', $a, $user, 'attachments');
            }
            $this->log->log('ajukan', "Mengajukan laporan audit {$a->number}", $a, [], $user);
        });
        $this->notifyApprovers('audit_report_process', "Mohon pengesahan laporan audit {$a->number}", route('audits.show', $a));
    }

    public function decideReport(User $user, Audit $a, bool $approve, ?string $note): void
    {
        abort_unless($a->status === 'pengesahan' && $this->matrix->canApprove($user, 'audit_report_process', 'mutu.sahkan') && $a->auditor_id !== $user->id, 403);
        if (! $approve) {
            $this->requireNote($note);
            $a->update(['status' => 'pelaksanaan', 'approval_note' => $note]);
            $this->log->log('tolak', "Mengembalikan laporan audit {$a->number}: {$note}", $a, [], $user);

            return;
        }
        DB::transaction(function () use ($user, $a, $note) {
            $a->update(['status' => 'selesai'] + $a->recordApproval($user, $note));
            $path = $this->pdf->render('pdf.audit-report', ['a' => $a->fresh(['division', 'auditor', 'auditee', 'items', 'findings.ncr', 'program'])]);
            $file = $this->files->storeGenerated($path, 'pdf', 'application/pdf', $a->folderPath(), 'Laporan '.$a->number, 'audit_report', $a, $user);
            @unlink($path);
            $a->update(['file_id' => $file->id]);
            $this->log->log('setujui', "Mengesahkan laporan audit {$a->number}", $a, [], $user);
        });
    }

    public function cancel(User $user, Audit $a, string $note): void
    {
        abort_unless($user->can('audit.kelola') && in_array($a->status, ['rencana', 'pelaksanaan'], true), 403);
        $this->requireNote($note);
        $a->update(['status' => 'dibatalkan', 'cancel_reason' => $note]);
        $this->log->log('batal', "Membatalkan audit {$a->number}: {$note}", $a, [], $user);
    }

    private function notifyApprovers(string $key, string $subject, string $url): void
    {
        $this->notifier->send($this->matrix->approvers($key, 'mutu.sahkan'), new SmmNotification($subject, [$subject.'.'], 'Buka', $url, 'approval'));
    }

    private function requireNote(?string $note, string $field = 'note', string $msg = 'Catatan wajib diisi.'): void
    {
        if (trim((string) $note) === '') {
            throw ValidationException::withMessages([$field => $msg]);
        }
    }
}
