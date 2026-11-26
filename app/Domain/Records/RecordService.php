<?php

namespace App\Domain\Records;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Files\FileStore;
use App\Domain\Numbering\NumberGenerator;
use App\Models\Document;
use App\Models\QualityRecord;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Catatan mutu (bukti pelaksanaan). Nomor: [kode formulir]/[tahun]/[urut 4 digit], mis. F-OPS-03/2027/0045.
 * Tanpa formulir: CM/[tahun]/[urut]. Urutan terpisah per formulir dan kembali ke 1 tiap tahun.
 */
class RecordService
{
    public function __construct(private NumberGenerator $numbers, private FileStore $files, private ActivityLogger $log) {}

    /** @param UploadedFile[] $attachments */
    public function create(User $user, array $data, array $attachments): QualityRecord
    {
        if (! $user->can('catatan.catat')) {
            abort(403);
        }
        if (! $user->can('catatan.lihat-semua') && (int) $data['division_id'] !== (int) $user->division_id) {
            throw ValidationException::withMessages(['division_id' => 'Anda hanya bisa mencatat untuk divisi Anda.']);
        }
        $attachments = array_values(array_filter($attachments));
        if (! $attachments) {
            throw ValidationException::withMessages(['attachments' => 'Unggah minimal satu berkas bukti (scan formulir, foto, notulen, dll.).']);
        }
        $form = ! empty($data['document_id']) ? Document::find($data['document_id']) : null;
        if ($form && (! $form->current_revision_id || $form->type->code !== 'F')) {
            throw ValidationException::withMessages(['document_id' => 'Pilih formulir terkendali yang sedang berlaku.']);
        }

        return DB::transaction(function () use ($user, $data, $attachments, $form) {
            $date = Carbon::parse($data['activity_date']);
            $years = (int) ($data['retention_years'] ?? Settings::get('record.retention_years', 5));
            $formCode = $form ? preg_replace('/^ASI-/', '', $form->number) : 'CM';
            $record = QualityRecord::create([
                'number' => $this->numbers->next('record', ['formulir' => $formCode, 'tahun' => $date->format('Y')], $user->id),
                'document_id' => $form?->id,
                'training_class_id' => $data['training_class_id'] ?? null,
                'division_id' => $data['division_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'activity_date' => $date->toDateString(),
                'retention_years' => $years,
                'retention_until' => $date->copy()->addYears($years)->toDateString(),
                'evidence_category_id' => $data['evidence_category_id'] ?? 8,
                'created_by' => $user->id,
            ]);
            foreach ($attachments as $i => $file) {
                $this->files->store($file, ['pdf', 'jpg', 'png', 'docx', 'xlsx'], $record->folderPath(),
                    $record->title.' - '.($i + 1), 'record', $record, $user, 'attachments');
            }
            $this->log->log('unggah', "Mencatat {$record->number}: {$record->title}", $record, [], $user);

            return $record;
        });
    }

    /** @param UploadedFile[] $attachments */
    public function addFiles(User $user, QualityRecord $record, array $attachments): void
    {
        foreach (array_values(array_filter($attachments)) as $i => $file) {
            $this->files->store($file, ['pdf', 'jpg', 'png', 'docx', 'xlsx'], $record->folderPath(),
                $record->title.' - tambahan '.now()->format('YmdHis').'-'.($i + 1), 'record', $record, $user, 'attachments');
        }
        $this->log->log('unggah', "Menambah berkas pada {$record->number}", $record, [], $user);
    }

    public function archive(User $user, QualityRecord $record): void
    {
        $record->update(['archived_at' => now()]);
        $this->log->log('arsip', "Mengarsipkan {$record->number}", $record, [], $user);
    }
}
