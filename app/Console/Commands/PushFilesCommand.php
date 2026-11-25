<?php

namespace App\Console\Commands;

use App\Domain\Files\FileStore;
use App\Models\StoredFile;
use Illuminate\Console\Command;
use Throwable;

class PushFilesCommand extends Command
{
    protected $signature = 'smm:files-push {--failed : Ulangi juga berkas berstatus gagal}';

    protected $description = 'Pindahkan berkas yang masih tertunda di server ke Google Drive';

    public function handle(FileStore $store): int
    {
        $states = $this->option('failed') ? [StoredFile::PENDING, StoredFile::FAILED] : [StoredFile::PENDING];
        $files = StoredFile::whereIn('state', $states)->where('updated_at', '<', now()->subMinutes(10))->limit(50)->get();
        $ok = 0;
        foreach ($files as $file) {
            try {
                if ($file->state === StoredFile::FAILED) {
                    $file->update(['attempts' => 0, 'state' => StoredFile::PENDING]);
                }
                $store->push($file->fresh());
                $ok++;
            } catch (Throwable $e) {
                $this->warn("Gagal {$file->uuid}: ".$e->getMessage());
            }
        }
        $this->info("{$ok}/{$files->count()} berkas dipindah ke Drive.");

        return self::SUCCESS;
    }
}
