<?php

namespace App\Domain\Notifications;

use App\Models\Complaint;
use App\Models\Document;
use App\Models\Ncr;
use App\Models\NcrAction;
use App\Models\NcrEffectivenessCheck;
use App\Models\Risk;
use App\Models\ExternalDocument;
use App\Models\ExternalDocumentVersion;
use App\Models\ReminderLog;
use App\Notifications\SmmNotification;
use App\Support\Format;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengingat harian:
 * - Dokumen eksternal: 90/60/30 hari sebelum berakhir + saat sudah kedaluwarsa.
 * - Dokumen terkendali: 30 hari sebelum tanggal tinjauan berikutnya.
 * Setiap pengingat dicatat di reminder_logs (unik) sehingga tidak terkirim ganda.
 */
class Reminders
{
    public function __construct(private Notifier $notifier) {}

    public function run(): array
    {
        return [
            'external' => $this->external(), 'review' => $this->reviews(), 'capa' => $this->capaActions(),
            'efektivitas' => $this->effectiveness(), 'keluhan' => $this->complaints(), 'risiko' => $this->risks(),
        ];
    }

    public function external(): int
    {
        $thresholds = collect(Settings::get('reminder.external_days', [90, 60, 30]))->map(fn ($d) => (int) $d)->sort()->values();
        $recipients = $this->notifier->usersWithPermission('legalitas.kelola');
        $sent = 0;

        ExternalDocument::notArchived()->whereNotNull('current_version_id')->with(['currentVersion', 'creator'])->chunkById(200, function ($docs) use ($thresholds, $recipients, &$sent) {
            foreach ($docs as $doc) {
                $v = $doc->currentVersion;
                if (! $v?->expires_at) {
                    continue;
                }
                $days = $v->daysLeft();
                if ($days < 0) {
                    $kind = 'exp-0';
                } else {
                    $t = $thresholds->first(fn ($t) => $days <= $t);
                    if ($t === null) {
                        continue;
                    }
                    $kind = 'exp-'.$t;
                }
                if (! $this->claim($v, $kind, $v->expires_at->toDateString())) {
                    continue;
                }
                $subject = $days < 0
                    ? "Kedaluwarsa: {$doc->number} {$doc->title}"
                    : "Segera berakhir ({$days} hari): {$doc->number} {$doc->title}";
                $this->notifier->send($recipients->push($doc->creator), new SmmNotification(
                    $subject,
                    [
                        "Dokumen: {$doc->title} ({$doc->number})",
                        "Nomor asli: {$v->original_number}",
                        'Berakhir: '.Format::date($v->expires_at).($days < 0 ? ' (sudah lewat '.abs($days).' hari)' : " (sisa {$days} hari)"),
                        'Segera proses perpanjangan dan unggah versi barunya di SMM.',
                    ],
                    'Buka dokumen', route('external-documents.show', $doc), $days < 0 ? 'danger' : 'warning'
                ));
                $sent++;
            }
        });

        return $sent;
    }

    public function reviews(): int
    {
        $days = (int) Settings::get('reminder.review_days', 30);
        $sent = 0;
        Document::notArchived()->whereNotNull('current_revision_id')->whereNotNull('next_review_date')
            ->whereDate('next_review_date', '<=', now()->addDays($days)->toDateString())
            ->with(['owner', 'currentRevision'])->chunkById(200, function ($docs) use ($days, &$sent) {
                foreach ($docs as $doc) {
                    if (! $this->claim($doc, 'review-'.$days, $doc->next_review_date->toDateString())) {
                        continue;
                    }
                    $this->notifier->send($doc->owner, new SmmNotification(
                        "Jadwal tinjauan dokumen: {$doc->number}",
                        [
                            "Dokumen \"{$doc->title}\" ({$doc->number} {$doc->currentRevision?->label()}) dijadwalkan ditinjau pada ".Format::date($doc->next_review_date).'.',
                            'Tinjau isinya; bila perlu perubahan, buat revisi baru di SMM.',
                        ],
                        'Buka dokumen', route('documents.show', $doc), 'warning'
                    ));
                    $sent++;
                }
            });

        return $sent;
    }

    /** Tindakan korektif: H-n sebelum tenggat dan saat lewat tenggat. */
    public function capaActions(): int
    {
        $days = (int) Settings::get('capa.reminder_days', 3);
        $sent = 0;
        NcrAction::with(['ncr.pic', 'pic'])->where('status', 'rencana')
            ->whereHas('ncr', fn ($q) => $q->where('status', Ncr::ACTION))
            ->whereDate('due_date', '<=', now()->addDays($days)->toDateString())
            ->chunkById(200, function ($actions) use (&$sent) {
                foreach ($actions as $a) {
                    $overdue = $a->due_date->lt(now()->startOfDay());
                    if (! $this->claim($a, $overdue ? 'overdue' : 'due-soon', $a->due_date->toDateString())) {
                        continue;
                    }
                    $this->notifier->send(collect([$a->pic, $a->ncr->pic]), new SmmNotification(
                        ($overdue ? 'Lewat tenggat' : 'Segera jatuh tempo').": tindakan {$a->ncr->number}",
                        ["Tindakan: {$a->description}", 'Tenggat: '.Format::date($a->due_date).'.', 'Tandai selesai dan unggah buktinya di SMM.'],
                        'Buka NCR', route('ncrs.show', $a->ncr), $overdue ? 'danger' : 'warning'
                    ));
                    $sent++;
                }
            });

        return $sent;
    }

    /** Jadwal pemantauan efektivitas 30/60/90 hari yang sudah tiba. */
    public function effectiveness(): int
    {
        $sent = 0;
        $verifiers = $this->notifier->usersWithPermission('ncr.verifikasi');
        NcrEffectivenessCheck::with('ncr')->whereNull('result')->whereDate('due_date', '<=', now()->toDateString())
            ->whereHas('ncr', fn ($q) => $q->where('status', Ncr::MONITORING))
            ->chunkById(200, function ($checks) use (&$sent, $verifiers) {
                foreach ($checks as $c) {
                    if (! $this->claim($c, 'eff-due', $c->due_date->toDateString())) {
                        continue;
                    }
                    $this->notifier->send($verifiers->where('id', '!=', $c->ncr->pic_id), new SmmNotification(
                        "Pemantauan efektivitas hari ke-{$c->day}: {$c->ncr->number}",
                        ['Saatnya memeriksa efektivitas tindakan korektif "'.$c->ncr->title."\" (hari ke-{$c->day}).", 'Catat hasil dan buktinya di SMM.'],
                        'Buka NCR', route('ncrs.show', $c->ncr), 'warning'
                    ));
                    $sent++;
                }
            });

        return $sent;
    }

    /** Keluhan yang melewati batas tanggapan. */
    public function complaints(): int
    {
        $sent = 0;
        Complaint::with(['pic', 'division'])->whereIn('status', ['baru', 'diproses'])->whereNotNull('response_due_date')
            ->whereDate('response_due_date', '<', now()->toDateString())
            ->chunkById(200, function ($items) use (&$sent) {
                foreach ($items as $c) {
                    if (! $this->claim($c, 'overdue', $c->response_due_date->toDateString())) {
                        continue;
                    }
                    $to = $c->pic ? collect([$c->pic]) : $this->notifier->usersWithPermission('keluhan.kelola');
                    $this->notifier->send($to, new SmmNotification(
                        "Keluhan lewat batas tanggapan: {$c->number}",
                        ["\"{$c->subject}\" belum ditanggapi. Batas: ".Format::date($c->response_due_date).'.'],
                        'Buka', route('complaints.show', $c), 'danger'
                    ));
                    $sent++;
                }
            });

        return $sent;
    }

    /** Tinjauan berkala risiko & peluang (30 hari sebelumnya). */
    public function risks(): int
    {
        $sent = 0;
        Risk::with('owner')->where('status', 'aktif')->whereNotNull('review_date')
            ->whereDate('review_date', '<=', now()->addDays(30)->toDateString())
            ->chunkById(200, function ($items) use (&$sent) {
                foreach ($items as $r) {
                    if (! $this->claim($r, 'review-30', $r->review_date->toDateString())) {
                        continue;
                    }
                    $this->notifier->send($r->owner, new SmmNotification(
                        "Tinjauan {$r->type}: {$r->number}",
                        ["{$r->title} dijadwalkan ditinjau pada ".Format::date($r->review_date).'. Lakukan penilaian ulang di SMM.'],
                        'Buka', route('risks.show', $r), 'warning'
                    ));
                    $sent++;
                }
            });

        return $sent;
    }

    /** Catat pengingat; false bila sudah pernah dikirim. */
    private function claim(Model $model, string $kind, string $targetDate): bool
    {
        $inserted = ReminderLog::query()->insertOrIgnore([
            'remindable_type' => $model->getMorphClass(),
            'remindable_id' => $model->getKey(),
            'kind' => $kind,
            'target_date' => $targetDate,
            'sent_at' => now(),
        ]);

        return $inserted > 0;
    }
}
