<?php

namespace App\Jobs;

use App\Domain\Files\FileStore;
use App\Models\StoredFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PushFileToDrive implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public int $fileId) {}

    public function handle(FileStore $store): void
    {
        $file = StoredFile::find($this->fileId);
        if ($file && $file->state !== StoredFile::STORED) {
            $store->push($file);
        }
    }
}
