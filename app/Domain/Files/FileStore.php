<?php

namespace App\Domain\Files;

use App\Domain\Drive\DriveFolders;
use App\Domain\Drive\DriveGateway;
use App\Jobs\PushFileToDrive;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Penyimpanan berkas:
 * 1. Unggahan divalidasi, diberi nama acak, disimpan sementara di storage/app/private/pending.
 * 2. Antrian (PushFileToDrive) mengunggah ke Google Drive, mencocokkan checksum MD5, lalu menghapus salinan lokal.
 * 3. Unduhan selalu lewat aplikasi: berkas diambil ke folder sementara lalu dikirim ke pengguna.
 */
class FileStore
{
    public function __construct(
        private FileValidator $validator,
        private PdfTools $pdf,
        private DriveGateway $drive,
        private DriveFolders $folders,
    ) {}

    /**
     * @param  string  $folderPath  jalur folder Drive di bawah folder induk SMM (mis. "Dokumen Terkendali/SOP/ASI-SOP-OPS-01")
     * @param  string  $baseName  nama berkas di Drive tanpa ekstensi (mis. "Rev 00 - Judul")
     */
    public function store(UploadedFile $upload, array $allowed, string $folderPath, string $baseName, string $purpose, ?Model $attachable, ?User $user, string $field = 'file', bool $requireWatermarkable = false): StoredFile
    {
        [$ext, $mime] = $this->validator->validate($upload, $allowed, $field);

        $uuid = (string) Str::uuid();
        $dir = storage_path('app/private/pending');
        File::ensureDirectoryExists($dir, 0700);
        $local = $dir.'/'.$uuid.'.'.$ext;

        $source = $upload->getRealPath();
        if ($ext === 'pdf' && $requireWatermarkable) {
            $source = $this->pdf->ensureWatermarkable($source, $field);
        }
        if (! @copy($source, $local)) {
            throw new RuntimeException('Gagal menyimpan berkas sementara.');
        }
        if ($source !== $upload->getRealPath()) {
            @unlink($source);
        }

        $file = StoredFile::create([
            'uuid' => $uuid,
            'original_name' => mb_substr($upload->getClientOriginalName(), 0, 250),
            'extension' => $ext,
            'mime' => $mime,
            'size' => filesize($local),
            'sha256' => hash_file('sha256', $local),
            'md5' => md5_file($local),
            'state' => StoredFile::PENDING,
            'local_path' => 'pending/'.$uuid.'.'.$ext,
            'drive_folder_path' => $this->cleanPath($folderPath),
            'drive_name' => $this->cleanName($baseName).'.'.$ext,
            'attachable_type' => $attachable?->getMorphClass(),
            'attachable_id' => $attachable?->getKey(),
            'purpose' => $purpose,
            'uploaded_by' => $user?->id,
        ]);

        DB::afterCommit(fn () => PushFileToDrive::dispatch($file->id));

        return $file;
    }

    public function attach(StoredFile $file, Model $attachable): void
    {
        $file->update(['attachable_type' => $attachable->getMorphClass(), 'attachable_id' => $attachable->getKey()]);
    }

    /** Unggah ke Drive. Dipanggil oleh antrian. */
    public function push(StoredFile $file): void
    {
        if ($file->state === StoredFile::STORED) {
            return;
        }
        $local = $this->localAbsolute($file);
        if (! $local || ! is_file($local)) {
            $file->update(['state' => StoredFile::FAILED, 'last_error' => 'Salinan lokal tidak ditemukan.']);

            return;
        }

        $file->increment('attempts');
        try {
            $folderId = $this->folders->idFor(config('smm.drive.root'), $file->drive_folder_path);
            $result = $this->drive->upload($local, $folderId, $file->drive_name, $file->mime);
            if ($result['md5'] && $result['md5'] !== $file->md5) {
                $this->drive->delete($result['id']);
                throw new RuntimeException('Checksum berkas di Drive tidak cocok.');
            }
            $file->update([
                'state' => StoredFile::STORED, 'drive_file_id' => $result['id'], 'stored_at' => now(),
                'local_path' => null, 'last_error' => null,
            ]);
            @unlink($local);
        } catch (Throwable $e) {
            $file->update(['state' => $file->attempts >= 5 ? StoredFile::FAILED : StoredFile::PENDING, 'last_error' => mb_substr($e->getMessage(), 0, 2000)]);
            throw $e;
        }
    }

    /** Salin isi berkas ke berkas sementara; pemanggil wajib menghapusnya setelah dipakai. */
    public function toTemp(StoredFile $file): string
    {
        $tmpDir = storage_path('app/private/tmp');
        File::ensureDirectoryExists($tmpDir, 0700);
        $tmp = $tmpDir.'/'.Str::random(32).'.'.$file->extension;

        $local = $this->localAbsolute($file);
        if ($local && is_file($local)) {
            File::copy($local, $tmp);
        } elseif ($file->drive_file_id) {
            $this->drive->downloadTo($file->drive_file_id, $tmp);
        } else {
            throw new RuntimeException('Isi berkas tidak tersedia.');
        }

        if (hash_file('sha256', $tmp) !== $file->sha256) {
            @unlink($tmp);
            throw new RuntimeException('Integritas berkas gagal diverifikasi (checksum berbeda).');
        }

        return $tmp;
    }

    public function localAbsolute(StoredFile $file): ?string
    {
        return $file->local_path ? storage_path('app/private/'.$file->local_path) : null;
    }

    public function cleanPath(string $path): string
    {
        return collect(explode('/', $path))->map(fn ($p) => $this->cleanName($p))->filter()->implode('/');
    }

    public function cleanName(string $name): string
    {
        $name = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', '-', $name);

        return trim(mb_substr(preg_replace('/\s+/', ' ', $name), 0, 150), ' .-');
    }
}
