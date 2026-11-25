<?php

namespace App\Domain\Drive;

/**
 * Antarmuka penyimpanan Drive. Modul lain hanya bergantung pada antarmuka ini,
 * bukan pada Google Drive langsung (implementasi: GoogleDriveGateway, LocalDriveGateway).
 */
interface DriveGateway
{
    /** Pastikan rangkaian folder ada di bawah folder induk ($root, mis. "SMM"); kembalikan ID folder terakhir. */
    public function ensureFolderPath(string $root, string $path): string;

    /** Unggah berkas lokal; kembalikan ['id' => ..., 'md5' => ..., 'size' => ...]. */
    public function upload(string $localPath, string $folderId, string $name, string $mime): array;

    /** Unduh isi berkas ke jalur lokal. */
    public function downloadTo(string $fileId, string $destPath): void;

    /** Metadata berkas (id, name, md5, size, createdTime) atau null jika tidak ada / di tempat sampah. */
    public function metadata(string $fileId): ?array;

    /** Daftar berkas (bukan folder) di dalam folder. */
    public function listFiles(string $folderId): array;

    /** Hapus berkas permanen (hanya dipakai untuk merapikan backup lama). */
    public function delete(string $fileId): void;

    /** Pemeriksaan koneksi; kembalikan info singkat (akun, kuota) atau lempar exception. */
    public function health(): array;

    /** Apakah sudah terhubung / siap dipakai. */
    public function isConnected(): bool;
}
