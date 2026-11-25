<?php

namespace App\Domain\Drive;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Google Drive lewat REST API v3 (tanpa pustaka google/apiclient yang besar).
 * Unggah memakai resumable upload per potongan 8 MB sehingga hemat memori di hosting.
 */
class GoogleDriveGateway implements DriveGateway
{
    private const API = 'https://www.googleapis.com/drive/v3';
    private const UPLOAD = 'https://www.googleapis.com/upload/drive/v3/files';
    private const FOLDER_MIME = 'application/vnd.google-apps.folder';
    private const CHUNK = 8 * 1024 * 1024; // kelipatan 256 KB

    public function __construct(private GoogleOAuth $oauth) {}

    public function ensureFolderPath(string $root, string $path): string
    {
        $parent = 'root';
        foreach (array_filter(explode('/', trim($root.'/'.trim($path, '/'), '/')), 'strlen') as $name) {
            $parent = $this->findOrCreateFolder($name, $parent);
        }

        return $parent;
    }

    public function findOrCreateFolder(string $name, string $parentId): string
    {
        $q = sprintf("name = '%s' and mimeType = '%s' and '%s' in parents and trashed = false",
            $this->escape($name), self::FOLDER_MIME, $this->escape($parentId));
        $res = $this->request()->get(self::API.'/files', ['q' => $q, 'fields' => 'files(id,name)', 'pageSize' => 10, 'spaces' => 'drive']);
        $this->ensureOk($res, 'mencari folder');
        if ($id = $res->json('files.0.id')) {
            return $id;
        }
        $res = $this->request()->post(self::API.'/files?fields=id', ['name' => $name, 'mimeType' => self::FOLDER_MIME, 'parents' => [$parentId]]);
        $this->ensureOk($res, 'membuat folder');

        return $res->json('id');
    }

    public function upload(string $localPath, string $folderId, string $name, string $mime): array
    {
        $size = filesize($localPath);
        $init = $this->request()
            ->withHeaders(['X-Upload-Content-Type' => $mime, 'X-Upload-Content-Length' => (string) $size])
            ->post(self::UPLOAD.'?uploadType=resumable&fields=id,md5Checksum,size', ['name' => $name, 'parents' => [$folderId]]);
        $this->ensureOk($init, 'memulai unggah');
        $session = $init->header('Location');

        $fh = fopen($localPath, 'rb');
        $offset = 0;
        $result = null;
        try {
            while ($offset < $size || $size === 0) {
                $chunk = $size === 0 ? '' : fread($fh, self::CHUNK);
                $len = strlen($chunk);
                $end = $offset + $len - 1;
                $range = $size === 0 ? 'bytes */0' : "bytes {$offset}-{$end}/{$size}";
                $res = Http::withToken($this->oauth->accessToken())->timeout(300)
                    ->withHeaders(['Content-Range' => $range])
                    ->withBody($chunk, $mime)
                    ->put($session);
                if ($res->status() === 308) {
                    $offset += $len;

                    continue;
                }
                $this->ensureOk($res, 'mengunggah berkas');
                $result = $res->json();
                break;
            }
        } finally {
            fclose($fh);
        }

        return ['id' => $result['id'], 'md5' => $result['md5Checksum'] ?? null, 'size' => (int) ($result['size'] ?? $size)];
    }

    public function downloadTo(string $fileId, string $destPath): void
    {
        @mkdir(dirname($destPath), 0700, true);
        $res = Http::withToken($this->oauth->accessToken())->timeout(600)
            ->withOptions(['sink' => $destPath])
            ->get(self::API.'/files/'.rawurlencode($fileId), ['alt' => 'media']);
        if (! $res->successful()) {
            @unlink($destPath);
            $this->ensureOk($res, 'mengunduh berkas');
        }
    }

    public function metadata(string $fileId): ?array
    {
        $res = $this->request()->get(self::API.'/files/'.rawurlencode($fileId), ['fields' => 'id,name,md5Checksum,size,createdTime,trashed']);
        if ($res->status() === 404) {
            return null;
        }
        $this->ensureOk($res, 'membaca metadata');
        if ($res->json('trashed')) {
            return null;
        }

        return ['id' => $res->json('id'), 'name' => $res->json('name'), 'md5' => $res->json('md5Checksum'), 'size' => (int) $res->json('size'), 'createdTime' => $res->json('createdTime')];
    }

    public function listFiles(string $folderId): array
    {
        $files = [];
        $pageToken = null;
        do {
            $res = $this->request()->get(self::API.'/files', array_filter([
                'q' => sprintf("'%s' in parents and trashed = false and mimeType != '%s'", $this->escape($folderId), self::FOLDER_MIME),
                'fields' => 'nextPageToken,files(id,name,size,createdTime)',
                'pageSize' => 1000,
                'orderBy' => 'createdTime',
                'pageToken' => $pageToken,
            ]));
            $this->ensureOk($res, 'membaca isi folder');
            foreach ($res->json('files', []) as $f) {
                $files[] = ['id' => $f['id'], 'name' => $f['name'], 'size' => (int) ($f['size'] ?? 0), 'createdTime' => $f['createdTime'] ?? null];
            }
            $pageToken = $res->json('nextPageToken');
        } while ($pageToken);

        return $files;
    }

    public function delete(string $fileId): void
    {
        $res = $this->request()->delete(self::API.'/files/'.rawurlencode($fileId));
        if ($res->status() !== 404) {
            $this->ensureOk($res, 'menghapus berkas');
        }
    }

    public function health(): array
    {
        $res = $this->request()->get(self::API.'/about', ['fields' => 'user(emailAddress),storageQuota(limit,usage)']);
        $this->ensureOk($res, 'memeriksa koneksi');

        return ['account' => $res->json('user.emailAddress'), 'quota' => $res->json('storageQuota')];
    }

    public function isConnected(): bool
    {
        return $this->oauth->isConfigured() && $this->oauth->hasToken();
    }

    private function request(): PendingRequest
    {
        // Ulangi hanya untuk gangguan sementara (jaringan, 429, 5xx), bukan 404/403.
        return Http::withToken($this->oauth->accessToken())->acceptJson()->timeout(60)->retry(3, 1000, function (\Throwable $e) {
            if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
                return true;
            }
            $status = $e instanceof \Illuminate\Http\Client\RequestException ? $e->response->status() : 0;

            return $status === 429 || $status >= 500;
        }, throw: false);
    }

    private function ensureOk(Response $res, string $what): void
    {
        if ($res->status() === 401) {
            $this->oauth->forgetAccessToken();
        }
        if (! $res->successful()) {
            throw new DriveException("Google Drive gagal {$what} (HTTP {$res->status()}): ".mb_substr($res->body(), 0, 500));
        }
    }

    private function escape(string $v): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $v);
    }
}
