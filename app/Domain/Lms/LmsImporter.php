<?php

namespace App\Domain\Lms;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Complaints\ComplaintService;
use App\Models\Complaint;
use App\Models\LmsImport;
use App\Models\SurveyResponse;
use App\Models\TrainingClass;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Menyimpan rekap kelas & respons survei dari LmsSource ke SMM.
 * Kode kelas LMS dipakai apa adanya (unik & tetap). Data pribadi peserta tidak disalin.
 */
class LmsImporter
{
    public const SURVEY_TYPES = [
        'kepuasan_peserta' => 'kepuasan_peserta', 'kepuasan' => 'kepuasan_peserta', 'peserta' => 'kepuasan_peserta',
        'evaluasi_trainer' => 'evaluasi_trainer', 'trainer' => 'evaluasi_trainer', 'instruktur' => 'evaluasi_trainer',
        'evaluasi_klien' => 'evaluasi_klien', 'klien' => 'evaluasi_klien', 'perusahaan' => 'evaluasi_klien',
    ];

    public function __construct(private ActivityLogger $log) {}

    public function importClasses(LmsSource $source, ?User $user, string $sourceType = 'csv'): LmsImport
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $errors = [];
        $programs = TrainingProgram::all();

        foreach ($source->classes() as ['row' => $row, 'data' => $d]) {
            try {
                $code = trim((string) ($d['class_code'] ?? ''));
                if ($code === '') {
                    throw new \InvalidArgumentException('kode kelas kosong');
                }
                $programName = trim((string) ($d['program_name'] ?? ''));
                $program = $programs->first(fn ($p) => ($d['program_code'] ?? null) && ($p->code === (string) $d['program_code'] || $p->lms_program_id === (string) $d['program_code']))
                    ?? ($programName !== '' ? $programs->first(fn ($p) => Str::lower($p->name) === Str::lower($programName)) : null);

                $values = array_filter([
                    'program_name' => $programName !== '' ? $programName : null,
                    'training_program_id' => $program?->id,
                    'scheme' => $d['scheme'] ?? null,
                    'client' => $d['client'] ?? null,
                    'location' => $d['location'] ?? null,
                    'start_date' => isset($d['start_date']) ? FieldMap::date($d['start_date']) : null,
                    'end_date' => isset($d['end_date']) ? FieldMap::date($d['end_date']) : null,
                    'trainers' => isset($d['trainers']) ? Str::limit((string) $d['trainers'], 250, '') : null,
                    'participants' => isset($d['participants']) ? (int) FieldMap::number($d['participants']) : null,
                    'attended' => isset($d['attended']) ? (int) FieldMap::number($d['attended']) : null,
                    'passed' => isset($d['passed']) ? (int) FieldMap::number($d['passed']) : null,
                    'average_score' => isset($d['average_score']) ? FieldMap::number($d['average_score']) : null,
                    'lms_status' => $d['lms_status'] ?? null,
                ], fn ($v) => $v !== null);
                if (isset($values['participants'], $values['attended']) && $values['attended'] > $values['participants']) {
                    throw new \InvalidArgumentException('jumlah hadir melebihi jumlah peserta');
                }

                $class = TrainingClass::firstWhere('class_code', $code);
                if ($class) {
                    $class->update($values + ['source' => $sourceType, 'synced_at' => now()]);
                    $stats['updated']++;
                } else {
                    if (empty($values['program_name'])) {
                        throw new \InvalidArgumentException('nama program/pelatihan kosong');
                    }
                    TrainingClass::create($values + ['class_code' => $code, 'source' => $sourceType, 'synced_at' => now()]);
                    $stats['created']++;
                }
            } catch (Throwable $e) {
                $stats['skipped']++;
                $errors[] = "Baris {$row}: ".$e->getMessage();
            }
        }

        return $this->finish('kelas', $sourceType, $source, $stats, $errors, $user);
    }

    public function importSurveys(LmsSource $source, ?User $user, string $sourceType = 'csv'): LmsImport
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $errors = [];
        $threshold = (float) Settings::get('survey.negative_threshold', 2.5);
        $import = LmsImport::create(['kind' => 'survei', 'source' => $sourceType, 'file_name' => $source->label(), 'imported_by' => $user?->id]);

        foreach ($source->surveys() as ['row' => $row, 'data' => $d]) {
            try {
                $type = self::SURVEY_TYPES[FieldMap::key((string) ($d['survey_type'] ?? ''))] ?? null;
                if (! $type) {
                    throw new \InvalidArgumentException('jenis survei harus kepuasan_peserta / evaluasi_trainer / evaluasi_klien');
                }
                $aspects = $d['aspects'] ?? [];
                $score = isset($d['score']) ? FieldMap::number($d['score']) : ($aspects ? array_sum($aspects) / count($aspects) : null);
                if ($score === null || $score < 1 || $score > 5) {
                    throw new \InvalidArgumentException('skor harus 1–5');
                }
                $externalId = isset($d['external_id']) ? $sourceType.':'.$d['external_id'] : null;
                if ($externalId && SurveyResponse::where('external_id', $externalId)->exists()) {
                    $stats['skipped']++;

                    continue; // sudah pernah diimpor
                }
                $code = trim((string) ($d['class_code'] ?? ''));
                $comment = isset($d['comment']) ? Str::limit(trim((string) $d['comment']), 2000, '') : null;
                SurveyResponse::create([
                    'training_class_id' => $code !== '' ? TrainingClass::where('class_code', $code)->value('id') : null,
                    'class_code' => $code ?: null,
                    'survey_type' => $type,
                    'responded_at' => isset($d['responded_at']) ? FieldMap::date($d['responded_at']) : now()->toDateString(),
                    'score' => round($score, 2),
                    'aspects' => $aspects ?: null,
                    'comment' => $comment ?: null,
                    'subject' => isset($d['subject']) ? Str::limit((string) $d['subject'], 250, '') : null,
                    'is_negative' => $score <= $threshold,
                    'external_id' => $externalId,
                    'lms_import_id' => $import->id,
                ]);
                $stats['created']++;
            } catch (Throwable $e) {
                $stats['skipped']++;
                $errors[] = "Baris {$row}: ".$e->getMessage();
            }
        }

        return $this->finish('survei', $sourceType, $source, $stats, $errors, $user, $import);
    }

    /** Keluhan dari formulir LMS → Keluhan SMM (sekali per nomor tiket). */
    public function importComplaints(LmsSource $source, ?User $user, string $sourceType = 'api'): LmsImport
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $errors = [];
        $import = LmsImport::create(['kind' => 'keluhan', 'source' => $sourceType, 'file_name' => $source->label(), 'imported_by' => $user?->id]);
        $service = app(ComplaintService::class);

        foreach ($source->complaints() as ['row' => $row, 'data' => $d]) {
            try {
                $ticket = trim((string) ($d['external_id'] ?? ''));
                $text = trim((string) ($d['description'] ?? ''));
                if ($ticket === '' || $text === '') {
                    throw new \InvalidArgumentException('nomor tiket & uraian wajib');
                }
                $externalId = 'lms:'.Str::limit($ticket, 90, '');
                if (Complaint::where('external_id', $externalId)->exists()) {
                    $stats['skipped']++;

                    continue; // sudah pernah ditarik
                }
                $service->importFromLms([
                    'external_id' => $externalId,
                    'received_at' => isset($d['received_at']) ? FieldMap::date($d['received_at']) : now()->toDateString(),
                    'class_code' => Str::limit(trim((string) ($d['class_code'] ?? '')), 100, '') ?: null,
                    'category' => FieldMap::key((string) ($d['category'] ?? '')),
                    'description' => Str::limit($text, 5000, ''),
                ]);
                $stats['created']++;
            } catch (Throwable $e) {
                $stats['skipped']++;
                $errors[] = "Baris {$row}: ".$e->getMessage();
            }
        }

        return $this->finish('keluhan', $sourceType, $source, $stats, $errors, $user, $import);
    }

    private function finish(string $kind, string $sourceType, LmsSource $source, array $stats, array $errors, ?User $user, ?LmsImport $import = null): LmsImport
    {
        $import ??= new LmsImport(['kind' => $kind, 'source' => $sourceType, 'file_name' => $source->label(), 'imported_by' => $user?->id]);
        $import->fill([
            'created_count' => $stats['created'], 'updated_count' => $stats['updated'], 'skipped_count' => $stats['skipped'],
            'errors' => array_slice($errors, 0, 200) ?: null,
        ])->save();
        $this->log->log('sistem', "Impor {$kind} dari {$source->label()}: {$stats['created']} baru, {$stats['updated']} diperbarui, {$stats['skipped']} dilewati", $import, [], $user);

        return $import;
    }
}
