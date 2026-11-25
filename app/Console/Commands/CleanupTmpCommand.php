<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupTmpCommand extends Command
{
    protected $signature = 'smm:cleanup-tmp';

    protected $description = 'Hapus berkas sementara yang lebih tua dari 2 jam (menjaga ruang hosting)';

    public function handle(): int
    {
        $dir = storage_path('app/private/tmp');
        $n = 0;
        if (is_dir($dir)) {
            foreach (File::allFiles($dir, true) as $f) {
                if ($f->getMTime() < time() - 7200) {
                    @unlink($f->getPathname());
                    $n++;
                }
            }
            foreach (File::directories($dir) as $d) {
                if (filemtime($d) < time() - 7200) {
                    File::deleteDirectory($d);
                }
            }
        }
        $this->info("{$n} berkas sementara dihapus.");

        return self::SUCCESS;
    }
}
