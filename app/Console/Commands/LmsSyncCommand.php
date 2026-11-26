<?php

namespace App\Console\Commands;

use App\Domain\Lms\ApiLmsSource;
use App\Domain\Lms\LmsImporter;
use Illuminate\Console\Command;

class LmsSyncCommand extends Command
{
    protected $signature = 'smm:lms-sync {--days=30 : Ambil data sejak N hari lalu}';

    protected $description = 'Tarik rekap kelas, survei & keluhan dari API LMS (baca-saja). Dilewati bila API belum dikonfigurasi.';

    public function handle(LmsImporter $importer): int
    {
        if (! ApiLmsSource::configured()) {
            $this->line('API LMS belum dikonfigurasi (SMM_LMS_API_URL/TOKEN). Gunakan impor CSV.');

            return self::SUCCESS;
        }
        $since = now()->subDays((int) $this->option('days'))->toDateString();
        $failed = false;
        // Tiap jenis ditarik terpisah: bila satu endpoint bermasalah, yang lain tetap masuk.
        foreach (['Kelas' => 'importClasses', 'Survei' => 'importSurveys', 'Keluhan' => 'importComplaints'] as $label => $method) {
            try {
                $r = $importer->{$method}(ApiLmsSource::fromConfig($since), null, 'api');
                $this->info("{$label}: {$r->created_count} baru, {$r->updated_count} diperbarui, {$r->skipped_count} dilewati.");
            } catch (\Throwable $e) {
                $failed = true;
                report($e);
                $this->error("{$label}: gagal – ".$e->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
