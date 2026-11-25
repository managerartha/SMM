<?php

namespace App\Domain\ExternalDocuments;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Files\FileStore;
use App\Domain\Numbering\NumberGenerator;
use App\Models\ExternalDocument;
use App\Models\ExternalDocumentCategory;
use App\Models\ExternalDocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Legalitas & Kontrak. Dokumen eksternal tidak disahkan; diverifikasi keasliannya oleh GM.
 * Perpanjangan = versi baru di bawah nomor internal yang sama; versi lama tetap tersimpan.
 */
class ExternalDocumentService
{
    public function __construct(private NumberGenerator $numbers, private FileStore $files, private ActivityLogger $log) {}

    public function create(User $user, array $data, UploadedFile $file): ExternalDocument
    {
        Gate::forUser($user)->authorize('create', ExternalDocument::class);
        $cat = ExternalDocumentCategory::findOrFail($data['external_document_category_id']);

        return DB::transaction(function () use ($user, $data, $file, $cat) {
            $number = $this->numbers->next('external_document', ['kategori' => $cat->code], $user->id);
            $doc = ExternalDocument::create([
                'number' => $number,
                'external_document_category_id' => $cat->id,
                'title' => $data['title'],
                'issuer' => $data['issuer'],
                'evidence_category_id' => $data['evidence_category_id'] ?? $cat->default_evidence_category_id,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);
            $version = $this->addVersion($user, $doc, $data, $file);
            $this->log->log('buat', "Menambah dokumen eksternal {$number} – {$doc->title}", $doc, ['version' => $version->version_no], $user);

            return $doc;
        });
    }

    public function renew(User $user, ExternalDocument $doc, array $data, UploadedFile $file): ExternalDocumentVersion
    {
        Gate::forUser($user)->authorize('update', $doc);

        return DB::transaction(function () use ($user, $doc, $data, $file) {
            $doc = ExternalDocument::whereKey($doc->id)->lockForUpdate()->first();
            $version = $this->addVersion($user, $doc, $data, $file);
            $this->log->log('perpanjang', "Menambah versi {$version->version_no} (perpanjangan/pembaruan) {$doc->number}", $doc, [], $user);

            return $version;
        });
    }

    public function updateInfo(User $user, ExternalDocument $doc, array $data): void
    {
        Gate::forUser($user)->authorize('update', $doc);
        $doc->update(array_intersect_key($data, array_flip(['title', 'issuer', 'evidence_category_id', 'notes'])));
        $this->log->log('ubah', "Mengubah info dokumen eksternal {$doc->number}", $doc, [], $user);
    }

    public function verify(User $user, ExternalDocumentVersion $version): void
    {
        Gate::forUser($user)->authorize('verify', $version->externalDocument);
        if ($version->verified_at) {
            return;
        }
        $version->update(['verified_by' => $user->id, 'verified_at' => now()]);
        $this->log->log('verifikasi', "Memverifikasi keaslian {$version->externalDocument->number} versi {$version->version_no}", $version->externalDocument, [], $user);
    }

    public function archive(User $user, ExternalDocument $doc, string $note): void
    {
        Gate::forUser($user)->authorize('archive', $doc);
        $doc->update(['archived_at' => now(), 'archived_by' => $user->id]);
        $this->log->log('arsip', "Mengarsipkan dokumen eksternal {$doc->number}: {$note}", $doc, [], $user);
    }

    public function restore(User $user, ExternalDocument $doc): void
    {
        Gate::forUser($user)->authorize('archive', $doc);
        $doc->update(['archived_at' => null, 'archived_by' => null]);
        $this->log->log('pulihkan', "Memulihkan dokumen eksternal {$doc->number}", $doc, [], $user);
    }

    public function download(User $user, ExternalDocumentVersion $version): BinaryFileResponse
    {
        $doc = $version->externalDocument;
        Gate::forUser($user)->authorize('view', $doc);
        $file = $version->file;
        $tmp = $this->files->toTemp($file);
        $this->log->log('unduh', "Mengunduh {$doc->number} versi {$version->version_no}", $doc, ['file' => $file->uuid], $user);
        $name = $this->files->cleanName("{$doc->number} v{$version->version_no} - {$doc->title}").'.'.$file->extension;

        return response()->download($tmp, $name, ['Content-Type' => $file->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'])
            ->deleteFileAfterSend();
    }

    private function addVersion(User $user, ExternalDocument $doc, array $data, UploadedFile $file): ExternalDocumentVersion
    {
        $no = (int) $doc->versions()->max('version_no') + 1;
        $stored = $this->files->store(
            $file, ['pdf', 'jpg', 'png', 'docx', 'xlsx'],
            'Legalitas/'.$doc->category->code.'/'.$doc->number,
            "Versi {$no} - {$data['original_number']}",
            'external_version', null, $user
        );
        $version = $doc->versions()->create([
            'version_no' => $no,
            'original_number' => $data['original_number'],
            'issued_at' => $data['issued_at'],
            'expires_at' => $data['expires_at'] ?? null,
            'file_id' => $stored->id,
            'notes' => $data['version_notes'] ?? null,
            'created_by' => $user->id,
        ]);
        $this->files->attach($stored, $version);

        if (! empty($data['verified']) && $user->can('legalitas.verifikasi')) {
            $version->update(['verified_by' => $user->id, 'verified_at' => now()]);
        }
        $doc->update(['current_version_id' => $version->id]);

        return $version;
    }
}
