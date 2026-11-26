<?php

namespace App\Console\Commands;

use App\Domain\Lms\ApiLmsSource;
use App\Domain\Lms\LmsImporter;
use Illuminate\Console\Command;

class LmsSyncCommand extends Command
{
    protected $signature = 'smm:lms-sync {--days=30 : Ambil data sejak N hari lalu}';

    protected $description = 'Tarik rekap kelas & survei dari API LMS (baca-saja). Dilewati bila API belum dikonfigurasi.';

    public function handle(LmsImporter $importer): int
    {
        if (! ApiLmsSource::configured()) {
            $this->line('API LMS belum dikonfigurasi (SMM_LMS_API_URL/TOKEN). Gunakan impor CSV.');

            return self::SUCCESS;
        }
        $since = now()->subDays((int) $this->option('days'))->toDateString();
        $a = $importer->importClasses(ApiLmsSource::fromConfig($since), null, 'api');
        $b = $importer->importSurveys(ApiLmsSource::fromConfig($since), null, 'api');
        $this->info("Kelas: {$a->created_count} baru, {$a->updated_count} diperbarui. Survei: {$b->created_count} baru.");

        return self::SUCCESS;
    }
}
