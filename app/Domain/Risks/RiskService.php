<?php

namespace App\Domain\Risks;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Numbering\NumberGenerator;
use App\Models\Risk;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Register risiko & peluang (ISO 9001 klausul 6.1). Setiap penilaian ulang disimpan sebagai riwayat. */
class RiskService
{
    public function __construct(private NumberGenerator $numbers, private ActivityLogger $log) {}

    public function create(User $user, array $data): Risk
    {
        Gate::forUser($user)->authorize('create', Risk::class);
        if (! $user->can('risiko.kelola') && (int) $data['division_id'] !== (int) $user->division_id) {
            throw ValidationException::withMessages(['division_id' => 'Anda hanya boleh mengelola risiko divisi Anda.']);
        }

        return DB::transaction(function () use ($user, $data) {
            $score = RiskLevel::score((int) $data['likelihood'], (int) $data['impact']);
            $risk = Risk::create($this->fields($data) + [
                'number' => $this->numbers->next('risk', [], $user->id),
                'type' => $data['type'],
                'division_id' => $data['division_id'],
                'likelihood' => (int) $data['likelihood'],
                'impact' => (int) $data['impact'],
                'score' => $score,
                'level' => RiskLevel::fromScore($score)->value,
                'owner_id' => $data['owner_id'] ?? $user->id,
                'review_date' => $data['review_date'] ?? now()->addMonths((int) Settings::get('risk.review_months', 12))->toDateString(),
                'status' => 'aktif',
                'created_by' => $user->id,
            ]);
            $risk->assessments()->create([
                'likelihood' => $risk->likelihood, 'impact' => $risk->impact, 'score' => $score, 'level' => $risk->level,
                'note' => 'Penilaian awal', 'assessed_by' => $user->id, 'assessed_at' => now(),
            ]);
            $this->log->log('buat', "Mencatat {$risk->type} {$risk->number}: {$risk->title} (skor {$score}, {$risk->level})", $risk, [], $user);

            return $risk;
        });
    }

    public function update(User $user, Risk $risk, array $data): void
    {
        Gate::forUser($user)->authorize('manage', $risk);
        $risk->update($this->fields($data) + array_intersect_key($data, array_flip(['owner_id', 'review_date'])));
        $this->log->log('ubah', "Mengubah {$risk->number}", $risk, [], $user);
    }

    public function reassess(User $user, Risk $risk, int $likelihood, int $impact, string $note): void
    {
        Gate::forUser($user)->authorize('manage', $risk);
        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => 'Catatan penilaian ulang wajib diisi (mis. hasil tindakan, perubahan kondisi).']);
        }
        $score = RiskLevel::score($likelihood, $impact);
        $level = RiskLevel::fromScore($score)->value;
        DB::transaction(function () use ($user, $risk, $likelihood, $impact, $note, $score, $level) {
            $before = "{$risk->score} ({$risk->level})";
            $risk->update([
                'likelihood' => $likelihood, 'impact' => $impact, 'score' => $score, 'level' => $level,
                'review_date' => now()->addMonths((int) Settings::get('risk.review_months', 12))->toDateString(),
            ]);
            $risk->assessments()->create([
                'likelihood' => $likelihood, 'impact' => $impact, 'score' => $score, 'level' => $level,
                'note' => $note, 'assessed_by' => $user->id, 'assessed_at' => now(),
            ]);
            $this->log->log('ubah', "Menilai ulang {$risk->number}: {$before} → {$score} ({$level})", $risk, [], $user);
        });
    }

    public function close(User $user, Risk $risk, string $note): void
    {
        Gate::forUser($user)->authorize('manage', $risk);
        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => 'Alasan penutupan wajib diisi.']);
        }
        $risk->update(['status' => 'ditutup', 'close_note' => $note, 'closed_at' => now()]);
        $this->log->log('arsip', "Menutup {$risk->number}: {$note}", $risk, [], $user);
    }

    public function reopen(User $user, Risk $risk): void
    {
        Gate::forUser($user)->authorize('manage', $risk);
        $risk->update(['status' => 'aktif', 'closed_at' => null]);
        $this->log->log('pulihkan', "Membuka kembali {$risk->number}", $risk, [], $user);
    }

    private function fields(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'process', 'title', 'description', 'cause', 'consequence', 'existing_controls', 'treatment',
            'treatment_pic_id', 'treatment_due_date', 'treatment_status', 'evidence_category_id',
        ]));
    }
}
