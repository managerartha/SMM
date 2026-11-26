<?php

namespace App\Domain\Complaints;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Capa\CapaWorkflow;
use App\Domain\Files\FileStore;
use App\Domain\Notifications\Notifier;
use App\Domain\Numbering\NumberGenerator;
use App\Models\Complaint;
use App\Models\Ncr;
use App\Models\User;
use App\Notifications\SmmNotification;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Keluhan, feedback, dan saran: dicatat → ditugaskan (diproses) → ditanggapi (selesai) | dibatalkan.
 * Keluhan bisa dinaikkan menjadi NCR.
 */
class ComplaintService
{
    public function __construct(
        private NumberGenerator $numbers,
        private FileStore $files,
        private ActivityLogger $log,
        private Notifier $notifier,
    ) {}

    /** @param UploadedFile[] $attachments */
    public function create(User $user, array $data, array $attachments = []): Complaint
    {
        Gate::forUser($user)->authorize('create', Complaint::class);

        return DB::transaction(function () use ($user, $data, $attachments) {
            $received = Carbon::parse($data['received_at']);
            $c = Complaint::create([
                'number' => $this->numbers->next('complaint', ['tahun' => $received->format('Y')], $user->id),
                'type' => $data['type'],
                'source' => $data['source'],
                'channel' => $data['channel'] ?? null,
                'received_at' => $received->toDateString(),
                'reporter_name' => $data['reporter_name'] ?? null,
                'reporter_contact' => $data['reporter_contact'] ?? null,
                'reporter_org' => $data['reporter_org'] ?? null,
                'class_code' => $data['class_code'] ?? null,
                'category' => $data['category'] ?? null,
                'division_id' => $data['division_id'] ?? null,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'severity' => $data['type'] === 'keluhan' ? ($data['severity'] ?? 'sedang') : null,
                'response_due_date' => $data['type'] === 'keluhan' ? $received->copy()->addDays((int) Settings::get('complaint.response_days', 3))->toDateString() : null,
                'status' => 'baru',
                'evidence_category_id' => 11,
                'created_by' => $user->id,
            ]);
            $this->attach($c, $attachments, $user);
            $this->log->log('buat', "Mencatat {$c->type} {$c->number}: {$c->subject}", $c, [], $user);

            DB::afterCommit(fn () => $this->notifier->send($this->handlers($c), new SmmNotification(
                ucfirst($c->type)." baru: {$c->number}",
                ["{$user->name} mencatat {$c->type} \"{$c->subject}\"".($c->reporter_name ? " dari {$c->reporter_name}" : '').'.',
                    $c->response_due_date ? 'Batas tanggapan: '.$c->response_due_date->format('d-m-Y').'.' : 'Mohon ditindaklanjuti.'],
                'Buka', route('complaints.show', $c), $c->type === 'keluhan' ? 'warning' : 'info'
            )));

            return $c;
        });
    }

    public function update(User $user, Complaint $c, array $data): void
    {
        Gate::forUser($user)->authorize('update', $c);
        $c->update(array_intersect_key($data, array_flip([
            'source', 'channel', 'reporter_name', 'reporter_contact', 'reporter_org', 'class_code', 'category', 'division_id',
            'subject', 'description', 'severity', 'response_due_date',
        ])));
        $this->log->log('ubah', "Mengubah {$c->number}", $c, [], $user);
    }

    public function assign(User $user, Complaint $c, int $picId, ?string $dueDate = null): void
    {
        Gate::forUser($user)->authorize('assign', $c);
        $pic = User::find($picId);
        if (! $pic || ! $pic->canAccess()) {
            throw ValidationException::withMessages(['pic_id' => 'Pilih penanggung jawab yang aktif.']);
        }
        $c->update(['pic_id' => $pic->id, 'status' => 'diproses'] + ($dueDate ? ['response_due_date' => $dueDate] : []));
        $this->log->log('ubah', "Menugaskan {$c->number} kepada {$pic->name}", $c, [], $user);
        $this->notifier->send($pic, new SmmNotification(
            "Ditugaskan menangani {$c->number}",
            ["{$user->name} menugaskan Anda menangani {$c->type} \"{$c->subject}\".",
                $c->response_due_date ? 'Batas tanggapan: '.$c->response_due_date->format('d-m-Y').'.' : ''],
            'Buka', route('complaints.show', $c), 'warning'
        ));
    }

    /** @param UploadedFile[] $attachments */
    public function respond(User $user, Complaint $c, string $response, ?string $resolution, array $attachments = []): void
    {
        Gate::forUser($user)->authorize('handle', $c);
        if (trim($response) === '') {
            throw ValidationException::withMessages(['response' => 'Tanggapan kepada pelapor wajib diisi.']);
        }
        DB::transaction(function () use ($user, $c, $response, $resolution, $attachments) {
            $c->update([
                'response' => $response, 'resolution' => $resolution, 'responded_at' => now(),
                'status' => 'selesai', 'closed_at' => now(), 'closed_by' => $user->id, 'pic_id' => $c->pic_id ?? $user->id,
            ]);
            $this->attach($c, $attachments, $user);
            $this->log->log('setujui', "Menyelesaikan {$c->number}", $c, [], $user);
        });
        if ($c->created_by !== $user->id) {
            $this->notifier->send($c->creator, new SmmNotification("Selesai ditangani: {$c->number}", ["{$c->subject} telah ditanggapi oleh {$user->name}."], 'Buka', route('complaints.show', $c)));
        }
    }

    public function cancel(User $user, Complaint $c, string $note): void
    {
        Gate::forUser($user)->authorize('assign', $c);
        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => 'Alasan pembatalan wajib diisi.']);
        }
        $c->update(['status' => 'dibatalkan', 'resolution' => 'Dibatalkan: '.$note, 'closed_at' => now(), 'closed_by' => $user->id]);
        $this->log->log('batal', "Membatalkan {$c->number}: {$note}", $c, [], $user);
    }

    public function escalate(User $user, Complaint $c, array $ncrData): Ncr
    {
        Gate::forUser($user)->authorize('escalate', $c);

        return DB::transaction(function () use ($user, $c, $ncrData) {
            $ncr = app(CapaWorkflow::class)->create($user, $ncrData + [
                'source' => 'keluhan',
                'complaint_id' => $c->id,
                'title' => $ncrData['title'] ?? $c->subject,
                'description' => $ncrData['description'] ?? $c->description,
                'division_id' => $ncrData['division_id'] ?? $c->division_id,
                'found_at' => $c->received_at->toDateString(),
            ]);
            $c->update(['ncr_id' => $ncr->id] + ($c->status === 'baru' ? ['status' => 'diproses'] : []));
            $this->log->log('buat', "Menaikkan {$c->number} menjadi {$ncr->number}", $c, ['ncr' => $ncr->number], $user);

            return $ncr;
        });
    }

    /**
     * Keluhan dari formulir LMS (ditarik otomatis, dicatat oleh "sistem").
     * Kontak pelapor tetap di LMS; yang disimpan hanya nomor tiket, kelas, kategori, dan uraian (sudah disamarkan LMS).
     */
    public function importFromLms(array $d): Complaint
    {
        return DB::transaction(function () use ($d) {
            $received = Carbon::parse($d['received_at']);
            $category = self::LMS_CATEGORIES[$d['category'] ?? ''] ?? 'lainnya';
            $c = Complaint::create([
                'number' => $this->numbers->next('complaint', ['tahun' => $received->format('Y')]),
                'type' => 'keluhan', 'source' => 'peserta', 'channel' => 'lms',
                'received_at' => $received->toDateString(),
                'class_code' => $d['class_code'] ?? null,
                'external_id' => $d['external_id'],
                'category' => $category,
                'subject' => 'Keluhan dari LMS – '.Complaint::CATEGORIES[$category].(! empty($d['class_code']) ? ' ('.$d['class_code'].')' : ''),
                'description' => trim($d['description'])."\n\nNomor tiket LMS: ".substr($d['external_id'], 4).'. Kontak pelapor ada di LMS (minta ke Admin LMS bila perlu menghubungi).',
                'severity' => 'sedang',
                'response_due_date' => $received->copy()->addDays((int) Settings::get('complaint.response_days', 3))->toDateString(),
                'status' => 'baru',
                'evidence_category_id' => 11,
                'created_by' => null,
            ]);
            $this->log->log('buat', "Keluhan {$c->number} masuk dari LMS (tiket ".substr($c->external_id, 4).')', $c);
            DB::afterCommit(fn () => $this->notifier->send($this->handlers($c), new SmmNotification(
                "Keluhan baru dari LMS: {$c->number}",
                ["Kategori: ".Complaint::CATEGORIES[$c->category].'.', 'Batas tanggapan: '.$c->response_due_date->format('d-m-Y').'.'],
                'Buka', route('complaints.show', $c), 'warning'
            )));

            return $c;
        });
    }

    /** Kategori keluhan di LMS → kategori SMM */
    public const LMS_CATEGORIES = ['materi' => 'materi', 'trainer' => 'trainer', 'fasilitas' => 'sarana', 'pelayanan' => 'pelayanan',
        'jadwal' => 'jadwal', 'dokumen' => 'sertifikat', 'lainnya' => 'lainnya'];

    /** @param UploadedFile[] $attachments */
    private function attach(Complaint $c, array $attachments, User $user): void
    {
        foreach (array_filter($attachments) as $i => $file) {
            $this->files->store($file, ['pdf', 'jpg', 'png', 'docx', 'xlsx'], 'Keluhan/'.str_replace('/', '-', $c->number),
                'Lampiran '.now()->format('Ymd-His').'-'.($i + 1), 'complaint_attachment', $c, $user, 'attachments');
        }
    }

    private function handlers(Complaint $c)
    {
        return $this->notifier->usersWithPermission('keluhan.kelola')
            ->merge($this->notifier->usersWithPermission('keluhan.kelola-divisi')->where('division_id', $c->division_id));
    }
}
