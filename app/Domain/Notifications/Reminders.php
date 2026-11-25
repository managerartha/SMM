<?php

namespace App\Domain\Notifications;

use App\Models\Document;
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
        return ['external' => $this->external(), 'review' => $this->reviews()];
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
