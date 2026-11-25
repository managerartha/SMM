<?php

namespace App\Domain\Drive;

use App\Models\DriveFolder;

/** Cache jalur folder → ID folder Drive (mengurangi panggilan API). */
class DriveFolders
{
    public function __construct(private DriveGateway $drive) {}

    public function idFor(string $root, string $path): string
    {
        $full = trim($root.'/'.trim($path, '/'), '/');
        $hash = hash('sha256', config('smm.drive.driver').':'.$full);
        if ($cached = DriveFolder::where('path_hash', $hash)->value('drive_id')) {
            return $cached;
        }
        $id = $this->drive->ensureFolderPath($root, $path);
        DriveFolder::updateOrCreate(['path_hash' => $hash], ['path' => $full, 'drive_id' => $id]);

        return $id;
    }

    public function forget(): void
    {
        DriveFolder::query()->delete();
    }
}
