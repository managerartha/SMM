<?php

namespace App\Domain\Innovations;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Approval\ApprovalMatrix;
use App\Domain\Files\FileStore;
use App\Domain\Notifications\Notifier;
use App\Domain\Numbering\NumberGenerator;
use App\Models\Innovation;
use App\Models\User;
use App\Notifications\SmmNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Program inovasi mutu INV/[tahun]/[urut]: usulan → persetujuan (tanpa anggaran: GM; dengan anggaran: Direktur) → pelaksanaan → hasil. */
class InnovationService
{
    public function __construct(private NumberGenerator $numbers, private ActivityLogger $log, private ApprovalMatrix $matrix, private Notifier $notifier, private FileStore $files) {}

    public function propose(User $user, array $data): Innovation
    {
        abort_unless($user->can('inovasi.usul'), 403);
        $i = Innovation::create($data + ['number' => $this->numbers->next('innovation', [], $user->id), 'proposer_id' => $user->id, 'status' => 'usulan', 'created_by' => $user->id]);
        $this->log->log('buat', "Mengusulkan inovasi {$i->number}: {$i->title}", $i, [], $user);
        $this->notifier->send($this->matrix->approvers($i->approvalKey(), 'mutu.sahkan'), new SmmNotification("Usulan inovasi {$i->number}", ["{$user->name} mengusulkan \"{$i->title}\"".($i->budget ? ' dengan anggaran Rp '.number_format($i->budget, 0, ',', '.') : ' (tanpa anggaran)').'.'], 'Buka', route('innovations.show', $i), 'approval'));

        return $i;
    }

    public function update(User $user, Innovation $i, array $data): void
    {
        abort_unless($i->status === 'usulan' && ($i->proposer_id === $user->id || $user->can('inovasi.kelola')), 403);
        $i->update($data);
    }

    public function decide(User $user, Innovation $i, bool $approve, ?string $note): void
    {
        abort_unless($i->status === 'usulan' && $this->matrix->canApprove($user, $i->approvalKey(), 'mutu.sahkan'), 403);
        if (! $approve && trim((string) $note) === '') {
            throw ValidationException::withMessages(['note' => 'Alasan penolakan wajib diisi.']);
        }
        $i->update(['status' => $approve ? 'disetujui' : 'ditolak'] + $i->recordApproval($user, $note));
        $this->log->log($approve ? 'setujui' : 'tolak', ($approve ? 'Menyetujui' : 'Menolak')." inovasi {$i->number}", $i, [], $user);
        $this->notifier->send(collect([$i->proposer, $i->pic]), new SmmNotification("Inovasi {$i->number} ".($approve ? 'disetujui' : 'ditolak'), [$i->title.($note ? ' – '.$note : '')], 'Buka', route('innovations.show', $i), $approve ? 'success' : 'warning'));
    }

    public function start(User $user, Innovation $i): void
    {
        abort_unless($i->status === 'disetujui' && ($i->pic_id === $user->id || $user->can('inovasi.kelola')), 403);
        $i->update(['status' => 'pelaksanaan', 'start_date' => $i->start_date ?? now()->toDateString()]);
        $this->log->log('ubah', "Memulai pelaksanaan inovasi {$i->number}", $i, [], $user);
    }

    /** @param UploadedFile[] $evidence */
    public function addEvidence(User $user, Innovation $i, array $evidence): void
    {
        abort_unless(in_array($i->status, ['pelaksanaan', 'selesai'], true) && ($i->pic_id === $user->id || $i->proposer_id === $user->id || $user->can('inovasi.kelola')), 403);
        foreach (array_values(array_filter($evidence)) as $n => $f) {
            $this->files->store($f, ['pdf', 'jpg', 'png', 'docx', 'xlsx'], $i->folderPath(), 'Bukti '.now()->format('YmdHis').'-'.($n + 1), 'innovation_evidence', $i, $user, 'evidence');
        }
        $this->log->log('unggah', "Menambah bukti inovasi {$i->number}", $i, [], $user);
    }

    public function complete(User $user, Innovation $i, array $data): void
    {
        abort_unless($i->status === 'pelaksanaan' && ($i->pic_id === $user->id || $user->can('inovasi.kelola')), 403);
        if ($i->attachments()->doesntExist()) {
            throw ValidationException::withMessages(['result' => 'Unggah minimal satu bukti pelaksanaan sebelum diselesaikan.']);
        }
        DB::transaction(function () use ($user, $i, $data) {
            $i->update($data + ['status' => 'selesai', 'completed_at' => now(), 'end_date' => $i->end_date ?? now()->toDateString()]);
            $this->log->log('ubah', "Menyelesaikan inovasi {$i->number}", $i, [], $user);
        });
    }
}
