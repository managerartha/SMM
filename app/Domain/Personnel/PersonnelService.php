<?php

namespace App\Domain\Personnel;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Files\FileStore;
use App\Domain\Numbering\NumberGenerator;
use App\Models\Personnel;
use App\Models\PersonnelDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Personel (TA-xxx / TT-xxx) & berkasnya. Berkas yang diperbarui disimpan sebagai berkas baru; yang lama jadi riwayat. */
class PersonnelService
{
    public function __construct(private NumberGenerator $numbers, private FileStore $files, private ActivityLogger $log) {}

    public function create(User $user, array $data): Personnel
    {
        return DB::transaction(function () use ($user, $data) {
            $p = Personnel::create($data + [
                'number' => $this->numbers->next($data['type'] === 'tenaga_ahli' ? 'personnel_ta' : 'personnel_tt', [], $user->id),
                'created_by' => $user->id,
            ]);
            $this->log->log('buat', "Menambah personel {$p->number} – {$p->name}", $p, [], $user);

            return $p;
        });
    }

    public function update(User $user, Personnel $p, array $data): void
    {
        $p->update($data);
        $this->log->log('ubah', "Mengubah personel {$p->number}", $p, [], $user);
    }

    public function addDocument(User $user, Personnel $p, array $data, UploadedFile $file, ?PersonnelDocument $replaces = null): PersonnelDocument
    {
        return DB::transaction(function () use ($user, $p, $data, $file, $replaces) {
            $stored = $this->files->store($file, ['pdf', 'jpg', 'png', 'docx'], 'Personel/'.$p->number.' - '.$p->name,
                (PersonnelDocument::CATEGORIES[$data['category']] ?? $data['category']).' - '.$data['title'], 'personnel_document', null, $user, 'file');
            $doc = $p->documents()->create([
                'category' => $data['category'], 'title' => $data['title'], 'original_number' => $data['original_number'] ?? null,
                'issuer' => $data['issuer'] ?? null, 'issued_at' => $data['issued_at'] ?? null, 'expires_at' => $data['expires_at'] ?? null,
                'file_id' => $stored->id, 'created_by' => $user->id,
            ] + (! empty($data['verified']) && $user->can('personel.kelola') ? ['verified_by' => $user->id, 'verified_at' => now()] : []));
            $this->files->attach($stored, $p);
            if ($replaces) {
                $replaces->update(['replaced_by_id' => $doc->id]);
            }
            $this->log->log('unggah', "Mengunggah berkas {$doc->title} untuk {$p->number}".($replaces ? ' (memperbarui berkas lama)' : ''), $p, [], $user);

            return $doc;
        });
    }

    public function verify(User $user, PersonnelDocument $doc): void
    {
        $doc->update(['verified_by' => $user->id, 'verified_at' => now()]);
        $this->log->log('verifikasi', "Memverifikasi berkas {$doc->title} ({$doc->personnel->number})", $doc->personnel, [], $user);
    }
}
