<?php

namespace App\Domain\Capa;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Approval\ApprovalMatrix;
use App\Domain\Files\FileStore;
use App\Domain\Notifications\Notifier;
use App\Domain\Numbering\NumberGenerator;
use App\Models\Ncr;
use App\Models\NcrAction;
use App\Models\NcrEffectivenessCheck;
use App\Models\User;
use App\Notifications\SmmNotification;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Alur NCR & CAPA (ISO 9001 klausul 10.2):
 * Terbuka (koreksi + akar masalah + rencana tindakan) → Tindakan korektif → Verifikasi pelaksanaan
 * → Pemantauan efektivitas 30/60/90 hari → Menunggu penutupan → Ditutup (sesuai matriks "capa_closure").
 * Pemantauan "tidak efektif" atau penutupan ditolak → kembali Terbuka (siklus baru, akar masalah dianalisis ulang).
 */
class CapaWorkflow
{
    public function __construct(
        private NumberGenerator $numbers,
        private FileStore $files,
        private ActivityLogger $log,
        private Notifier $notifier,
        private ApprovalMatrix $matrix,
    ) {}

    /** @param UploadedFile[] $attachments */
    public function create(User $user, array $data, array $attachments = []): Ncr
    {
        Gate::forUser($user)->authorize('create', Ncr::class);
        $pic = $this->activeUser($data['pic_id'] ?? null, 'pic_id', 'Pilih penanggung jawab NCR yang aktif.');

        return DB::transaction(function () use ($user, $data, $attachments, $pic) {
            $found = Carbon::parse($data['found_at'] ?? now());
            $ncr = Ncr::create([
                'number' => $this->numbers->next('ncr', ['tahun' => now()->format('Y')], $user->id),
                'source' => $data['source'],
                'complaint_id' => $data['complaint_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'],
                'requirement' => $data['requirement'] ?? null,
                'division_id' => $data['division_id'],
                'severity' => $data['severity'] ?? 'minor',
                'found_at' => $found->toDateString(),
                'pic_id' => $pic->id,
                'target_date' => $data['target_date'] ?? now()->addDays(30)->toDateString(),
                'status' => Ncr::OPEN,
                'evidence_category_id' => 14,
                'created_by' => $user->id,
            ]);
            $this->attach($ncr, $ncr, $attachments, $user, 'Temuan');
            $this->event($ncr, 'dibuat', $user);
            $this->log->log('buat', "Membuat {$ncr->number}: {$ncr->title}", $ncr, [], $user);
            DB::afterCommit(fn () => $this->notifier->send($pic, new SmmNotification(
                "NCR baru untuk Anda: {$ncr->number}",
                ["{$user->name} membuat NCR \"{$ncr->title}\" dan menunjuk Anda sebagai penanggung jawab.",
                    'Isi koreksi, analisis akar masalah, dan rencana tindakan korektif. Target: '.$ncr->target_date->format('d-m-Y').'.'],
                'Buka NCR', route('ncrs.show', $ncr), 'warning'
            )));

            return $ncr;
        });
    }

    public function update(User $user, Ncr $ncr, array $data): void
    {
        Gate::forUser($user)->authorize('manage', $ncr);
        $fields = array_intersect_key($data, array_flip(['title', 'description', 'requirement', 'severity', 'target_date', 'division_id']));
        if (isset($data['pic_id']) && (int) $data['pic_id'] !== $ncr->pic_id) {
            $fields['pic_id'] = $this->activeUser($data['pic_id'], 'pic_id', 'Pilih penanggung jawab yang aktif.')->id;
        }
        $ncr->update($fields);
        $this->event($ncr, 'diubah', $user);
        $this->log->log('ubah', "Mengubah {$ncr->number}", $ncr, [], $user);
    }

    public function recordAnalysis(User $user, Ncr $ncr, ?string $correction, ?string $method, ?string $rootCause): void
    {
        Gate::forUser($user)->authorize('manage', $ncr);
        $this->ensureStatus($ncr, [Ncr::OPEN]);
        DB::transaction(function () use ($user, $ncr, $correction, $method, $rootCause) {
            if (trim((string) $correction) !== '' && $correction !== $ncr->correction) {
                $ncr->update(['correction' => $correction, 'correction_at' => now()]);
                $this->event($ncr, 'koreksi', $user, $correction);
            }
            if (trim((string) $rootCause) !== '' && $rootCause !== $ncr->root_cause) {
                $ncr->update(['root_cause_method' => $method, 'root_cause' => $rootCause, 'root_cause_at' => now()]);
                $this->event($ncr, 'akar_masalah', $user, $rootCause);
            }
            $this->log->log('ubah', "Mencatat koreksi/akar masalah {$ncr->number}", $ncr, [], $user);
        });
    }

    public function addAction(User $user, Ncr $ncr, string $description, int $picId, string $dueDate): NcrAction
    {
        Gate::forUser($user)->authorize('manage', $ncr);
        $this->ensureStatus($ncr, [Ncr::OPEN, Ncr::ACTION]);
        $pic = $this->activeUser($picId, 'action_pic_id', 'Pilih penanggung jawab tindakan yang aktif.');
        if (trim($description) === '') {
            throw ValidationException::withMessages(['action_description' => 'Uraian tindakan wajib diisi.']);
        }

        $action = $ncr->actions()->create([
            'cycle' => $ncr->currentCycle(), 'description' => $description, 'pic_id' => $pic->id,
            'due_date' => $dueDate, 'status' => 'rencana', 'created_by' => $user->id,
        ]);
        $this->event($ncr, 'tindakan_ditambah', $user, $description.' (PIC: '.$pic->name.', tenggat '.Carbon::parse($dueDate)->format('d-m-Y').')');
        $this->log->log('buat', "Menambah tindakan korektif pada {$ncr->number}", $ncr, [], $user);
        if ($ncr->status === Ncr::ACTION && $pic->id !== $user->id) {
            $this->notifyActionPic($action, $user);
        }

        return $action;
    }

    public function startActions(User $user, Ncr $ncr): void
    {
        Gate::forUser($user)->authorize('manage', $ncr);
        $this->ensureStatus($ncr, [Ncr::OPEN]);
        $missing = [];
        if (! $ncr->correction) {
            $missing[] = 'koreksi';
        }
        if (! $ncr->root_cause) {
            $missing[] = 'analisis akar masalah';
        }
        $actions = $ncr->actions()->where('cycle', $ncr->currentCycle())->where('status', 'rencana')->get();
        if ($actions->isEmpty()) {
            $missing[] = 'minimal satu rencana tindakan korektif';
        }
        if ($missing) {
            throw ValidationException::withMessages(['status' => 'Lengkapi dulu: '.implode(', ', $missing).'.']);
        }
        $ncr->update(['status' => Ncr::ACTION]);
        $this->event($ncr, 'mulai_tindakan', $user);
        $this->log->log('ajukan', "Memulai tindakan korektif {$ncr->number}", $ncr, [], $user);
        foreach ($actions as $a) {
            if ($a->pic_id !== $user->id) {
                $this->notifyActionPic($a, $user);
            }
        }
    }

    /** @param UploadedFile[] $evidence */
    public function completeAction(User $user, NcrAction $action, string $note, array $evidence = []): void
    {
        Gate::forUser($user)->authorize('completeAction', $action);
        if (trim($note) === '') {
            throw ValidationException::withMessages(['completion_note' => 'Uraikan apa yang sudah dilakukan.']);
        }
        $ncr = $action->ncr;
        DB::transaction(function () use ($user, $action, $note, $evidence, $ncr) {
            $action->update(['status' => 'selesai', 'completed_at' => now(), 'completion_note' => $note, 'completed_by' => $user->id]);
            $this->attach($ncr, $action, $evidence, $user, 'Tindakan '.$action->id);
            $this->event($ncr, 'tindakan_selesai', $user, $action->description.' → '.$note);
            $this->log->log('ubah', "Menyelesaikan tindakan korektif {$ncr->number}", $ncr, [], $user);
        });
        if (! $ncr->actions()->where('cycle', $ncr->currentCycle())->where('status', 'rencana')->exists()) {
            $this->notifier->send($this->notifier->usersWithPermission('ncr.verifikasi')->where('id', '!=', $ncr->pic_id), new SmmNotification(
                "Mohon verifikasi: {$ncr->number}",
                ["Semua tindakan korektif untuk \"{$ncr->title}\" telah selesai. Mohon verifikasi pelaksanaannya untuk memulai pemantauan efektivitas."],
                'Buka NCR', route('ncrs.show', $ncr), 'approval'
            ));
        }
    }

    public function verify(User $user, Ncr $ncr, string $note): void
    {
        Gate::forUser($user)->authorize('verify', $ncr);
        $this->ensureStatus($ncr, [Ncr::ACTION]);
        if ($ncr->actions()->where('cycle', $ncr->currentCycle())->where('status', 'rencana')->exists()) {
            throw ValidationException::withMessages(['status' => 'Masih ada tindakan korektif yang belum selesai.']);
        }
        if (trim($note) === '') {
            throw ValidationException::withMessages(['verification_note' => 'Catatan verifikasi wajib diisi.']);
        }
        DB::transaction(function () use ($user, $ncr, $note) {
            $ncr->update(['status' => Ncr::MONITORING, 'verification_note' => $note, 'verified_at' => now(), 'verified_by' => $user->id]);
            foreach ((array) Settings::get('capa.effectiveness_days', [30, 60, 90]) as $day) {
                NcrEffectivenessCheck::create([
                    'ncr_id' => $ncr->id, 'cycle' => $ncr->currentCycle(), 'day' => (int) $day,
                    'due_date' => now()->addDays((int) $day)->toDateString(),
                ]);
            }
            $this->event($ncr, 'diverifikasi', $user, $note);
            $this->log->log('verifikasi', "Memverifikasi pelaksanaan tindakan {$ncr->number}; pemantauan 30/60/90 hari dijadwalkan", $ncr, [], $user);
        });
    }

    /** @param UploadedFile[] $evidence */
    public function recordEffectiveness(User $user, NcrEffectivenessCheck $check, string $result, string $note, array $evidence = []): void
    {
        $ncr = $check->ncr;
        Gate::forUser($user)->authorize('verify', $ncr);
        $this->ensureStatus($ncr, [Ncr::MONITORING]);
        if ($check->cycle !== $ncr->currentCycle() || $check->result !== null) {
            throw ValidationException::withMessages(['result' => 'Pemantauan ini sudah diisi atau bukan siklus berjalan.']);
        }
        if (! $check->isOpenForInput()) {
            throw ValidationException::withMessages(['result' => 'Pemantauan hari ke-'.$check->day.' baru bisa diisi mulai '.$check->due_date->copy()->subDays(7)->format('d-m-Y').'.']);
        }
        $earlier = $ncr->checks()->where('cycle', $check->cycle)->where('day', '<', $check->day)->whereNull('result')->exists();
        if ($earlier) {
            throw ValidationException::withMessages(['result' => 'Isi pemantauan sebelumnya terlebih dahulu.']);
        }
        if (! in_array($result, ['efektif', 'tidak_efektif'], true) || trim($note) === '') {
            throw ValidationException::withMessages(['note' => 'Pilih hasil dan isi catatan pemantauan (data/bukti yang diperiksa).']);
        }

        DB::transaction(function () use ($user, $check, $result, $note, $evidence, $ncr) {
            $check->update(['result' => $result, 'note' => $note, 'checked_at' => now(), 'checked_by' => $user->id]);
            $this->attach($ncr, $check, $evidence, $user, 'Efektivitas hari '.$check->day);
            $this->event($ncr, 'efektivitas', $user, 'Hari ke-'.$check->day.': '.($result === 'efektif' ? 'EFEKTIF' : 'TIDAK EFEKTIF').' – '.$note);
            $this->log->log('verifikasi', "Pemantauan efektivitas {$ncr->number} hari ke-{$check->day}: {$result}", $ncr, [], $user);

            if ($result === 'tidak_efektif') {
                $this->reopen($ncr, $user, 'tidak_efektif', 'Pemantauan hari ke-'.$check->day.' tidak efektif: '.$note);

                return;
            }
            $pending = $ncr->checks()->where('cycle', $check->cycle)->whereNull('result')->exists();
            if (! $pending) {
                $ncr->update(['status' => Ncr::CLOSING]);
                DB::afterCommit(fn () => $this->notifier->send($this->matrix->approvers('capa_closure', 'ncr.tutup'), new SmmNotification(
                    "Mohon penutupan: {$ncr->number}",
                    ["Pemantauan efektivitas 30/60/90 hari untuk \"{$ncr->title}\" seluruhnya efektif. Mohon tinjau dan tutup NCR."],
                    'Buka NCR', route('ncrs.show', $ncr), 'approval'
                )));
            }
        });
    }

    public function close(User $user, Ncr $ncr, ?string $note): void
    {
        Gate::forUser($user)->authorize('close', $ncr);
        DB::transaction(function () use ($user, $ncr, $note) {
            $ncr->update([
                'status' => Ncr::CLOSED, 'closure_note' => $note, 'closed_at' => now(), 'closed_by' => $user->id,
                'closed_by_name' => $user->name, 'closed_by_position' => $user->position,
            ]);
            $this->event($ncr, 'ditutup', $user, $note);
            $this->log->log('setujui', "Menutup {$ncr->number}", $ncr, [], $user);
        });
        $this->notifier->send(collect([$ncr->pic, $ncr->creator]), new SmmNotification("Ditutup: {$ncr->number}", ["NCR \"{$ncr->title}\" telah ditutup oleh {$user->name}."], 'Buka NCR', route('ncrs.show', $ncr), 'success'));
    }

    public function rejectClosure(User $user, Ncr $ncr, string $note): void
    {
        Gate::forUser($user)->authorize('close', $ncr);
        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => 'Alasan penolakan wajib diisi.']);
        }
        DB::transaction(fn () => $this->reopen($ncr, $user, 'penutupan_ditolak', $note));
    }

    public function cancel(User $user, Ncr $ncr, string $note): void
    {
        Gate::forUser($user)->authorize('cancel', $ncr);
        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => 'Alasan pembatalan wajib diisi.']);
        }
        $ncr->update(['status' => Ncr::CANCELLED, 'cancel_reason' => $note]);
        $this->event($ncr, 'dibatalkan', $user, $note);
        $this->log->log('batal', "Membatalkan {$ncr->number}: {$note}", $ncr, [], $user);
    }

    // ------------------------------------------------------------------

    private function reopen(Ncr $ncr, User $user, string $event, string $note): void
    {
        // Jadwal pemantauan siklus ini yang belum diisi dibatalkan; akar masalah harus dianalisis ulang.
        $ncr->checks()->where('cycle', $ncr->currentCycle())->whereNull('result')->delete();
        $ncr->update([
            'status' => Ncr::OPEN, 'reopened_count' => $ncr->reopened_count + 1,
            'root_cause' => null, 'root_cause_at' => null, 'root_cause_method' => null,
            'verified_at' => null, 'verified_by' => null, 'verification_note' => null,
            'target_date' => now()->addDays(30)->toDateString(),
        ]);
        $this->event($ncr, $event, $user, $note);
        $this->log->log('tolak', "{$ncr->number} dibuka ulang (siklus {$ncr->currentCycle()}): {$note}", $ncr, [], $user);
        DB::afterCommit(fn () => $this->notifier->send($ncr->pic, new SmmNotification(
            "NCR dibuka ulang: {$ncr->number}",
            ["Tindakan korektif untuk \"{$ncr->title}\" dinilai belum efektif / penutupan ditolak.", "Catatan: {$note}", 'Lakukan analisis akar masalah ulang dan susun tindakan korektif baru.'],
            'Buka NCR', route('ncrs.show', $ncr), 'danger'
        )));
    }

    private function notifyActionPic(NcrAction $a, User $by): void
    {
        $this->notifier->send($a->pic, new SmmNotification(
            "Tindakan korektif untuk Anda: {$a->ncr->number}",
            ["{$by->name} menugaskan tindakan korektif: \"{$a->description}\".", 'Tenggat: '.$a->due_date->format('d-m-Y').'. Setelah selesai, tandai selesai dan unggah buktinya di SMM.'],
            'Buka NCR', route('ncrs.show', $a->ncr), 'warning'
        ));
    }

    /** @param UploadedFile[] $files */
    private function attach(Ncr $ncr, $attachable, array $files, User $user, string $label): void
    {
        foreach (array_filter($files) as $i => $file) {
            $this->files->store($file, ['pdf', 'jpg', 'png', 'docx', 'xlsx'], $ncr->folderPath(),
                $label.' - '.now()->format('Ymd-His').'-'.($i + 1), 'ncr_evidence', $attachable, $user, 'evidence');
        }
    }

    private function event(Ncr $ncr, string $action, User $user, ?string $note = null): void
    {
        $ncr->events()->create(['action' => $action, 'actor_id' => $user->id, 'actor_name' => $user->name, 'actor_position' => $user->position, 'note' => $note]);
    }

    private function ensureStatus(Ncr $ncr, array $allowed): void
    {
        if (! in_array($ncr->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'Aksi tidak bisa dilakukan pada status '.$ncr->statusLabel().'.']);
        }
    }

    private function activeUser(mixed $id, string $field, string $msg): User
    {
        $u = $id ? User::find($id) : null;
        if (! $u || ! $u->canAccess()) {
            throw ValidationException::withMessages([$field => $msg]);
        }

        return $u;
    }
}
