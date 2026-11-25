<?php

namespace Tests\Feature;

use App\Domain\Numbering\NumberGenerator;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NumberingTest extends TestCase
{
    use DatabaseMigrations;

    private function gen(): NumberGenerator
    {
        return app(NumberGenerator::class);
    }

    public function test_format_sesuai_spesifikasi(): void
    {
        $this->seedBase();
        $g = $this->gen();
        $this->assertSame('ASI-SOP-OPS-01', $g->next('document', ['jenis' => 'SOP', 'divisi' => 'OPS']));
        $this->assertSame('ASI-SOP-OPS-02', $g->next('document', ['jenis' => 'SOP', 'divisi' => 'OPS']));
        $this->assertSame('ASI-SOP-CS-01', $g->next('document', ['jenis' => 'SOP', 'divisi' => 'CS']), 'Urutan terpisah per jenis+divisi');
        $this->assertSame('ASI-IK-OPS-01', $g->next('document', ['jenis' => 'IK', 'divisi' => 'OPS']));
        $this->assertSame('NCR/2027/001', $g->next('ncr', ['tahun' => '2027']));
        $this->assertSame('KLH/2027/001', $g->next('complaint', ['tahun' => '2027']));
        $this->assertSame('AI/2027/01', $g->next('audit', ['tahun' => '2027']));
        $this->assertSame('DE-LGL-001', $g->next('external_document', ['kategori' => 'LGL']));
        $this->assertSame('F-OPS-03/2027/0045', $g->format('{formulir}/{tahun}/{urut:4}', ['formulir' => 'F-OPS-03', 'tahun' => '2027'], 45));
        $this->assertSame('TA-001', $g->next('personnel_ta'));
        $this->assertSame('INV/2027/01', $g->next('innovation', ['tahun' => '2027']));
        $this->assertSame('LKL/2027/03', $g->format(Settings::get('numbering.performance_report'), ['tahun' => '2027', 'bulan' => '03'], 1));
    }

    public function test_nomor_tahunan_kembali_ke_1_setiap_1_januari(): void
    {
        $this->seedBase();
        $this->travelTo('2027-12-31 23:00:00');
        $this->assertSame('NCR/2027/001', $this->gen()->next('ncr'));
        $this->assertSame('NCR/2027/002', $this->gen()->next('ncr'));
        $this->travelTo('2028-01-01 00:05:00');
        $this->assertSame('NCR/2028/001', $this->gen()->next('ncr'));
    }

    public function test_nomor_tidak_dipakai_ulang_walau_pola_diubah_atau_transaksi_dibatalkan(): void
    {
        $this->seedBase();
        $g = $this->gen();
        $this->assertSame('DE-SKP-001', $g->next('external_document', ['kategori' => 'SKP']));

        // Transaksi yang gagal membatalkan nomor juga (tidak ada nomor "hilang" tercatat ganda).
        try {
            DB::transaction(function () use ($g) {
                $g->next('external_document', ['kategori' => 'SKP']);
                throw new \RuntimeException('gagal simpan');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame('DE-SKP-002', $g->next('external_document', ['kategori' => 'SKP']));

        // Penghitung direset paksa (mis. pola diubah) → nomor yang sudah keluar dilewati.
        DB::table('number_sequences')->where('scope_key', 'like', 'external_document|%')->update(['last_value' => 0]);
        $this->assertSame('DE-SKP-003', $g->next('external_document', ['kategori' => 'SKP']));
        $this->assertSame(3, DB::table('issued_numbers')->where('object_type', 'external_document')->count());
    }

    public function test_tidak_ada_nomor_ganda_saat_disimpan_bersamaan(): void
    {
        $this->seedBase();
        $procs = [];
        $pipes = [];
        $workers = 6;
        $per = 15;
        for ($i = 0; $i < $workers; $i++) {
            $procs[$i] = proc_open([PHP_BINARY, base_path('tests/Support/number_worker.php'), (string) $per], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i], base_path(), $this->workerEnv());
        }
        $all = [];
        foreach ($procs as $i => $p) {
            $out = stream_get_contents($pipes[$i][1]);
            $err = stream_get_contents($pipes[$i][2]);
            proc_close($p);
            $nums = json_decode($out, true);
            $this->assertIsArray($nums, "Worker {$i} gagal: {$out} {$err}");
            $all = array_merge($all, $nums);
        }
        $this->assertCount($workers * $per, $all);
        $this->assertCount($workers * $per, array_unique($all), 'Ada nomor ganda!');
        sort($all);
        $this->assertSame('NCR/2027/001', $all[0]);
        $this->assertSame(sprintf('NCR/2027/%03d', $workers * $per), end($all), 'Nomor harus berurutan tanpa celah');
    }

    private function workerEnv(): array
    {
        $env = getenv();
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => config('database.connections.mysql.database'), 'CACHE_STORE' => 'array'] as $k => $v) {
            $env[$k] = $v;
        }

        return $env;
    }
}
