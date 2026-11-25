<?php

namespace App\Domain\Files;

use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Validasi unggahan berdasarkan ISI berkas (magic bytes / struktur), bukan hanya ekstensi.
 */
class FileValidator
{
    public const MIMES = [
        'pdf' => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'jpg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];

    /** @return array{0:string,1:string} [ekstensi, mime] */
    public function validate(UploadedFile $file, array $allowed, string $field = 'file'): array
    {
        $allowed = array_values(array_intersect($allowed, Settings::get('upload.allowed', array_keys(self::MIMES))));
        $maxMb = (int) Settings::get('upload.max_mb', 20);

        if (! $file->isValid()) {
            $this->fail($field, 'Berkas gagal diunggah. Periksa ukuran berkas (maks. '.$maxMb.' MB).');
        }
        if ($file->getSize() > $maxMb * 1024 * 1024) {
            $this->fail($field, "Ukuran berkas melebihi batas {$maxMb} MB.");
        }
        if ($file->getSize() === 0) {
            $this->fail($field, 'Berkas kosong.');
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if (! in_array($ext, $allowed, true)) {
            $this->fail($field, 'Jenis berkas tidak diizinkan. Yang diizinkan: '.strtoupper(implode(', ', $allowed)).'.');
        }

        $path = $file->getRealPath();
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';

        $ok = match ($ext) {
            'pdf' => $detected === 'application/pdf' && str_starts_with((string) file_get_contents($path, false, null, 0, 1024), '%PDF-'),
            'jpg' => $detected === 'image/jpeg' && @getimagesize($path) !== false,
            'png' => $detected === 'image/png' && @getimagesize($path) !== false,
            'docx' => $this->isOoxml($path, $detected, 'word/document.xml', self::MIMES['docx'][0]),
            'xlsx' => $this->isOoxml($path, $detected, 'xl/workbook.xml', self::MIMES['xlsx'][0]),
            default => false,
        };

        if (! $ok) {
            $this->fail($field, 'Isi berkas tidak sesuai dengan ekstensinya ('.strtoupper($ext).'). Berkas ditolak.');
        }

        return [$ext, self::MIMES[$ext][0]];
    }

    private function isOoxml(string $path, string $detected, string $marker, string $mime): bool
    {
        if (! in_array($detected, [$mime, 'application/zip', 'application/octet-stream'], true)) {
            return false;
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return false;
        }
        $has = $zip->locateName($marker) !== false && $zip->locateName('[Content_Types].xml') !== false;
        // Tolak dokumen bermakro (vbaProject) demi keamanan.
        $macro = $zip->locateName('word/vbaProject.bin') !== false || $zip->locateName('xl/vbaProject.bin') !== false;
        $zip->close();

        return $has && ! $macro;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
