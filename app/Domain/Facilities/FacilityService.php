<?php

namespace App\Domain\Facilities;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Files\FileStore;
use App\Domain\Numbering\NumberGenerator;
use App\Models\Facility;
use App\Models\FacilityLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Sarana & prasarana: inventaris (SP-[kategori]-[urut]), pemeriksaan berkala, kalibrasi, perbaikan. */
class FacilityService
{
    public function __construct(private NumberGenerator $numbers, private FileStore $files, private ActivityLogger $log) {}

    /** @param UploadedFile[] $photos */
    public function create(User $user, array $data, array $photos = []): Facility
    {
        return DB::transaction(function () use ($user, $data, $photos) {
            if (empty($data['next_inspection_at']) && ! empty($data['inspection_interval_months'])) {
                $data['next_inspection_at'] = now()->addMonths((int) $data['inspection_interval_months'])->toDateString();
            }
            $f = Facility::create($data + [
                'number' => $this->numbers->next('facility', ['kategori' => $data['category']], $user->id),
                'created_by' => $user->id,
            ]);
            $this->attach($f, $f, $photos, $user, 'Foto');
            $this->log->log('buat', "Menambah sarana {$f->number} – {$f->name}", $f, [], $user);

            return $f;
        });
    }

    public function update(User $user, Facility $f, array $data): void
    {
        $f->update($data);
        $this->log->log('ubah', "Mengubah sarana {$f->number}", $f, [], $user);
    }

    /** @param UploadedFile[] $evidence */
    public function addLog(User $user, Facility $f, array $data, array $evidence = []): FacilityLog
    {
        return DB::transaction(function () use ($user, $f, $data, $evidence) {
            $log = $f->logs()->create($data + ['performed_by' => $user->id]);
            $date = Carbon::parse($data['performed_at']);
            $updates = [];
            if (! empty($data['condition_after'])) {
                $updates['condition'] = $data['condition_after'];
            }
            if ($data['type'] === 'pemeriksaan' && $f->inspection_interval_months) {
                $updates['next_inspection_at'] = $date->copy()->addMonths($f->inspection_interval_months)->toDateString();
            }
            if ($data['type'] === 'kalibrasi' && ! empty($data['valid_until'])) {
                $updates['next_calibration_at'] = $data['valid_until'];
            }
            if ($updates) {
                $f->update($updates);
            }
            $this->attach($f, $log, $evidence, $user, ucfirst($data['type']).' '.$date->format('Y-m-d'));
            $this->log->log('ubah', "Mencatat {$data['type']} {$f->number}: ".mb_substr($data['result'], 0, 100), $f, [], $user);

            return $log;
        });
    }

    /** @param UploadedFile[] $files */
    public function attach(Facility $f, $attachable, array $files, User $user, string $label): void
    {
        foreach (array_values(array_filter($files)) as $i => $file) {
            $this->files->store($file, ['pdf', 'jpg', 'png'], $f->folderPath(), $label.' - '.now()->format('YmdHis').'-'.($i + 1), 'facility', $attachable, $user, 'photos');
        }
    }
}
