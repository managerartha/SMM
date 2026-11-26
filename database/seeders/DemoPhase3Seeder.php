<?php

namespace Database\Seeders;

use App\Domain\Facilities\FacilityService;
use App\Domain\Kpi\KpiCalculator;
use App\Domain\Kpi\KpiService;
use App\Domain\Lms\CsvLmsSource;
use App\Domain\Lms\LmsImporter;
use App\Domain\Personnel\PersonnelService;
use App\Domain\Records\RecordService;
use App\Domain\Suppliers\SupplierService;
use App\Models\Division;
use App\Models\Document;
use App\Models\TrainingClass;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/** Data contoh Tahap 3 (lokal saja). Kelas & survei dimasukkan lewat jalur impor CSV yang sama dengan produksi. */
class DemoPhase3Seeder extends Seeder
{
    public function run(): void
    {
        if (TrainingClass::exists()) {
            return;
        }
        config(['queue.default' => 'sync']);
        $gm = User::where('email', 'gm@contoh.test')->firstOrFail();
        $kadiv = User::where('email', 'kadiv.ops@contoh.test')->firstOrFail();
        $div = fn ($c) => Division::where('code', $c)->value('id');

        TrainingProgram::insert([
            ['code' => 'AK3U', 'name' => 'Ahli K3 Umum', 'institution' => 'Kemnaker RI', 'duration_days' => 12, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'P3K', 'name' => 'Petugas P3K', 'institution' => 'Kemnaker RI', 'duration_days' => 4, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'FIRE', 'name' => 'Petugas Peran Kebakaran', 'institution' => 'Kemnaker RI', 'duration_days' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $m = fn (int $back, int $day) => now()->subMonthsNoOverflow($back)->startOfMonth()->addDays($day - 1)->toDateString();
        $classCsv = "kode_kelas,program,kode_program,skema,klien,lokasi,tgl_mulai,tgl_selesai,trainer,jumlah_peserta,hadir,lulus,rata_nilai,status\n"
            ."AK3U-A01,Ahli K3 Umum,AK3U,Kemnaker RI,,Jakarta,{$m(2, 3)},{$m(2, 14)},Hendra Gunawan,25,24,23,81.5,selesai\n"
            ."P3K-B02,Petugas P3K,P3K,Kemnaker RI,PT Sentosa Logistik,Bekasi,{$m(1, 5)},{$m(1, 8)},Hendra Gunawan,20,20,19,84,selesai\n"
            ."FIRE-C03,Petugas Peran Kebakaran,FIRE,Kemnaker RI,,Jakarta,{$m(0, 2)},{$m(0, 4)},Agus Setiawan,18,16,16,79,selesai\n";
        $surveyCsv = "id_respons,kode_kelas,jenis_survei,tanggal,skor,aspek_materi,aspek_trainer,aspek_fasilitas,aspek_pelayanan,trainer,komentar,nama\n";
        $i = 0;
        foreach ([['AK3U-A01', 2, 15], ['P3K-B02', 1, 9], ['FIRE-C03', 0, 5]] as [$code, $back, $day]) {
            foreach ([[5, 5, 4, 5], [4, 5, 4, 4], [4, 4, 3, 4], [5, 5, 5, 5]] as $s) {
                $i++;
                $surveyCsv .= "R{$i},{$code},kepuasan_peserta,{$m($back, $day)},,{$s[0]},{$s[1]},{$s[2]},{$s[3]},,Materi bagus,Nama Peserta {$i}\n";
            }
            $i++;
            $surveyCsv .= "R{$i},{$code},evaluasi_trainer,{$m($back, $day)},4.6,,,,,Hendra Gunawan,Penjelasan jelas,\n";
        }
        $surveyCsv .= "R99,FIRE-C03,kepuasan_peserta,{$m(0, 5)},,2,3,1,2,,Ruang kelas panas dan AC mati,\n";
        $surveyCsv .= "R100,P3K-B02,evaluasi_klien,{$m(1, 12)},4.5,,,,,,Puas dengan pelayanan inhouse,\n";

        $importer = app(LmsImporter::class);
        foreach (['kelas' => $classCsv, 'survei' => $surveyCsv] as $kind => $csv) {
            $path = tempnam(sys_get_temp_dir(), 'lms');
            file_put_contents($path, $csv);
            $src = new CsvLmsSource($path, "contoh-{$kind}.csv");
            $kind === 'kelas' ? $importer->importClasses($src, $gm) : $importer->importSurveys($src, $gm);
        }

        $form = Document::where('number', 'ASI-F-OPS-01')->first();
        if ($form && ! $form->current_revision_id) {
            $form = null; // formulir contoh masih menunggu review
        }
        app(RecordService::class)->create($kadiv, [
            'document_id' => $form?->id, 'training_class_id' => TrainingClass::where('class_code', 'AK3U-A01')->value('id'),
            'division_id' => $div('OPS'), 'title' => 'Daftar hadir & laporan trainer kelas AK3U-A01', 'activity_date' => $m(2, 14),
        ], [$this->pdf('Daftar hadir')]);

        $ps = app(PersonnelService::class);
        $p1 = $ps->create($gm, ['type' => 'tenaga_ahli', 'name' => 'Hendra Gunawan', 'position' => 'Ahli K3 Umum / Trainer', 'is_trainer' => true, 'employment' => 'tetap', 'status' => 'aktif']);
        $ps->addDocument($gm, $p1, ['category' => 'lisensi', 'title' => 'SKP Ahli K3 Umum', 'original_number' => 'KEP.123/AK3U/2024', 'issuer' => 'Kemnaker RI', 'issued_at' => now()->subYears(3)->toDateString(), 'expires_at' => now()->addDays(45)->toDateString(), 'verified' => true], $this->pdf('SKP'));
        $ps->addDocument($gm, $p1, ['category' => 'cv', 'title' => 'CV Hendra Gunawan', 'verified' => true], $this->pdf('CV'));
        $ps->create($gm, ['type' => 'tenaga_teknis', 'name' => 'Rudi Hartono', 'position' => 'Teknisi alat praktik', 'is_trainer' => false, 'employment' => 'kontrak', 'status' => 'aktif']);

        $sup = app(SupplierService::class);
        $s1 = $sup->create($gm, ['name' => 'CV Mitra Kalibrasi', 'category' => 'kalibrasi', 'contact_person' => 'Bapak Joko', 'status' => 'aktif']);
        $sup->evaluate($gm, $s1, ['period' => now()->format('Y').'-S1', 'quality' => 4, 'timeliness' => 5, 'price' => 4, 'service' => 4, 'decision' => 'dipertahankan']);
        $sup->create($gm, ['name' => 'Hotel Graha Pelatihan', 'category' => 'venue', 'status' => 'aktif']);

        $fs = app(FacilityService::class);
        $f = $fs->create($gm, ['category' => 'ALT', 'name' => 'APAR 6 kg (alat praktik)', 'quantity' => 8, 'condition' => 'baik', 'location' => 'Gudang Jakarta', 'inspection_interval_months' => 6, 'next_inspection_at' => now()->addDays(10)->toDateString(), 'status' => 'aktif']);
        $fs->addLog($gm, $f, ['type' => 'pemeriksaan', 'performed_at' => now()->subMonths(5)->toDateString(), 'result' => 'Tekanan normal, segel utuh.']);
        $f->update(['next_inspection_at' => now()->addDays(10)->toDateString()]);
        $fs->create($gm, ['category' => 'ALT', 'name' => 'Manekin CPR dewasa', 'quantity' => 4, 'condition' => 'rusak_ringan', 'location' => 'Ruang kelas 1', 'status' => 'aktif']);
        $fs->create($gm, ['category' => 'ELK', 'name' => 'Gas detector 4-gas', 'quantity' => 2, 'condition' => 'baik', 'needs_calibration' => true, 'next_calibration_at' => now()->addMonths(4)->toDateString(), 'status' => 'aktif']);

        $ks = app(KpiService::class);
        $year = (int) now()->format('Y');
        $ks->create($gm, ['name' => 'Kepuasan peserta', 'objective' => 'Rata-rata kepuasan peserta minimal 4,2 dari 5', 'unit' => 'skala', 'target' => 4.2, 'comparator' => '>=', 'source' => 'survey_avg_peserta', 'division_id' => $div('OPS'), 'owner_id' => $kadiv->id, 'year' => $year, 'is_active' => true]);
        $ks->create($gm, ['name' => 'Kelulusan peserta', 'unit' => '%', 'target' => 90, 'comparator' => '>=', 'source' => 'class_pass_rate', 'division_id' => $div('OPS'), 'year' => $year, 'is_active' => true]);
        $ks->create($gm, ['name' => 'Keluhan ditanggapi tepat waktu', 'unit' => '%', 'target' => 95, 'comparator' => '>=', 'source' => 'complaint_ontime_pct', 'division_id' => $div('CS'), 'year' => $year, 'is_active' => true]);
        $cs = $ks->create($gm, ['name' => 'Waktu respons CS ke leads', 'objective' => 'Leads dibalas ≤ 15 menit (data dari sistem leads)', 'unit' => 'menit', 'target' => 15, 'comparator' => '<=', 'source' => 'manual', 'division_id' => $div('CS'), 'year' => $year, 'is_active' => true]);
        foreach ([3 => 18, 2 => 14, 1 => 12] as $back => $v) {
            $p = now()->subMonthsNoOverflow($back);
            if ((int) $p->format('Y') === $year) {
                $ks->setValue($gm, $cs, $p->format('Y-m'), $v, 'Rekap sistem leads');
            }
        }
        $calc = app(KpiCalculator::class);
        foreach ([2, 1, 0] as $back) {
            $calc->run(now()->subMonthsNoOverflow($back)->format('Y-m'));
        }
    }

    private function pdf(string $t): UploadedFile
    {
        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 14);
        $pdf->Cell(0, 10, $t.' (contoh)');
        $path = tempnam(sys_get_temp_dir(), 'smm').'.pdf';
        $pdf->Output('F', $path);

        return new UploadedFile($path, $t.'.pdf', 'application/pdf', null, true);
    }
}
