<?php

namespace App\Domain\Drive;

use Illuminate\Support\Facades\File;

/**
 * Pengganti Google Drive untuk komputer lokal dan tes otomatis.
 * Struktur folder sama persis, disimpan di storage/app/drive-local.
 * ID berkas = jalur relatif yang di-encode base64url.
 */
class LocalDriveGateway implements DriveGateway
{
    public function __construct(private string $base) {}

    public function ensureFolderPath(string $root, string $path): string
    {
        $rel = trim($root.'/'.trim($path, '/'), '/');
        File::ensureDirectoryExists($this->base.'/'.$rel);

        return $this->encode($rel);
    }

    public function upload(string $localPath, string $folderId, string $name, string $mime): array
    {
        $folder = $this->decode($folderId);
        $name = $this->uniqueName($folder, $name);
        $rel = $folder.'/'.$name;
        File::ensureDirectoryExists($this->base.'/'.$folder);
        File::copy($localPath, $this->base.'/'.$rel);

        return ['id' => $this->encode($rel), 'md5' => md5_file($this->base.'/'.$rel), 'size' => filesize($this->base.'/'.$rel)];
    }

    public function downloadTo(string $fileId, string $destPath): void
    {
        $src = $this->base.'/'.$this->decode($fileId);
        if (! is_file($src)) {
            throw new DriveException('Berkas tidak ditemukan di penyimpanan.');
        }
        File::ensureDirectoryExists(dirname($destPath));
        File::copy($src, $destPath);
    }

    public function metadata(string $fileId): ?array
    {
        $rel = $this->decode($fileId);
        $abs = $this->base.'/'.$rel;
        if (! is_file($abs)) {
            return null;
        }

        return ['id' => $fileId, 'name' => basename($rel), 'md5' => md5_file($abs), 'size' => filesize($abs), 'createdTime' => date('c', filemtime($abs))];
    }

    public function listFiles(string $folderId): array
    {
        $folder = $this->decode($folderId);
        $dir = $this->base.'/'.$folder;
        if (! is_dir($dir)) {
            return [];
        }

        return collect(File::files($dir))->map(fn ($f) => [
            'id' => $this->encode($folder.'/'.$f->getFilename()),
            'name' => $f->getFilename(),
            'size' => $f->getSize(),
            'createdTime' => date('c', $f->getMTime()),
        ])->values()->all();
    }

    public function delete(string $fileId): void
    {
        @unlink($this->base.'/'.$this->decode($fileId));
    }

    public function health(): array
    {
        File::ensureDirectoryExists($this->base);

        return ['account' => 'Penyimpanan lokal ('.$this->base.')', 'quota' => null];
    }

    public function isConnected(): bool
    {
        return true;
    }

    private function uniqueName(string $folder, string $name): string
    {
        $candidate = $name;
        $i = 2;
        while (is_file($this->base.'/'.$folder.'/'.$candidate)) {
            $candidate = pathinfo($name, PATHINFO_FILENAME)." ({$i}).".pathinfo($name, PATHINFO_EXTENSION);
            $i++;
        }

        return $candidate;
    }

    private function encode(string $rel): string
    {
        return rtrim(strtr(base64_encode($rel), '+/', '-_'), '=');
    }

    private function decode(string $id): string
    {
        $rel = base64_decode(strtr($id, '-_', '+/'));
        if ($rel === false || str_contains($rel, '..')) {
            throw new DriveException('ID berkas tidak valid.');
        }

        return $rel;
    }
}
