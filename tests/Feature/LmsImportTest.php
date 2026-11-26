<?php

namespace Tests\Feature;

use App\Domain\Lms\ApiLmsSource;
use App\Domain\Lms\CsvLmsSource;
use App\Domain\Lms\LmsImporter;
use App\Models\SurveyResponse;
use App\Models\TrainingClass;
use App\Models\TrainingProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class LmsImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $content, string $name = 'impor.csv'): CsvLmsSource
    {
        $p = tempnam(sys_get_temp_dir(), 'lmst');
        file_put_contents($p, $content);

        return new CsvLmsSource($p, $name);
    }

    public function test_impor_ekspor_tabel_batch_lms_dari_phpmyadmin(): void
    {
        $this->seedBase();
        TrainingProgram::create(['code' => 'AK3U', 'name' => 'Ahli K3 Umum']);
        // Ekspor phpMyAdmin: pemisah titik koma, judul kolom asli LMS, tanggal d/m/Y
        $src = $this->csv("id_batch;nama_pelatihan;kode_batch;tgl_mulai;tgl_selesai;jenis_pelatihan;perusahaan_inhouse;status\n"
            ."b1;Ahli K3 Umum;AK3U-01;06/01/2027;17/01/2027;Kemnaker RI;;aktif\n"
            ."b2;Petugas P3K;P3K-02;2027-02-03;2027-02-06;Kemnaker RI;PT Maju;selesai\n");
        $r = app(LmsImporter::class)->importClasses($src, $this->makeUser('gm'));
        $this->assertSame(2, $r->created_count);
        $c = TrainingClass::firstWhere('class_code', 'AK3U-01');
        $this->assertSame('2027-01-06', $c->start_date->toDateString());
        $this->assertSame('Kemnaker RI', $c->scheme);
        $this->assertNotNull($c->training_program_id, 'Dicocokkan dengan program induk lewat nama');
        $this->assertSame('PT Maju', TrainingClass::firstWhere('class_code', 'P3K-02')->client);
    }

    public function test_impor_ulang_memperbarui_bukan_duplikasi_dan_baris_salah_dilaporkan(): void
    {
        $this->seedBase();
        $imp = app(LmsImporter::class);
        $imp->importClasses($this->csv("kode_kelas,program,jumlah_peserta\nK-1,Ahli K3,20\n"), null);
        $r = $imp->importClasses($this->csv("kode_kelas,program,jumlah_peserta,hadir,lulus,tgl_mulai\nK-1,Ahli K3,20,19,18,2027-01-01\n,Tanpa kode,5,,,\nK-2,X,10,12,,\nK-3,Y,10,,,tanggal-ngawur\n"), null);
        $this->assertSame(0, $r->created_count);
        $this->assertSame(1, $r->updated_count);
        $this->assertSame(3, $r->skipped_count);
        $this->assertCount(3, $r->errors);
        $this->assertStringContainsString('kode kelas kosong', $r->errors[0]);
        $this->assertStringContainsString('hadir melebihi', $r->errors[1]);
        $this->assertSame(1, TrainingClass::count());
        $this->assertSame(18, TrainingClass::first()->passed);
    }

    public function test_impor_excel(): void
    {
        $this->seedBase();
        $p = tempnam(sys_get_temp_dir(), 'lmsx').'.xlsx';
        $w = new Writer;
        $w->openToFile($p);
        $w->addRow(Row::fromValues(['Kode Kelas', 'Program', 'Tgl Mulai', 'Jumlah Peserta', 'Rata Nilai']));
        $w->addRow(Row::fromValues(['XL-1', 'Petugas P3K', new \DateTimeImmutable('2027-03-02'), 15, 82.5]));
        $w->close();
        $r = app(LmsImporter::class)->importClasses(new CsvLmsSource($p, 'kelas.xlsx'), null);
        $this->assertSame(1, $r->created_count, implode(';', $r->errors ?? []));
        $this->assertSame('2027-03-02', TrainingClass::first()->start_date->toDateString());
        $this->assertEquals(82.5, TrainingClass::first()->average_score);
    }

    public function test_impor_survei_tanpa_data_pribadi_dan_tanpa_ganda(): void
    {
        $this->seedBase();
        TrainingClass::create(['class_code' => 'K-1', 'program_name' => 'X']);
        $csv = "id_respons,kode_kelas,jenis_survei,tanggal,skor,aspek_materi,aspek_fasilitas,komentar,nama,email,no_hp\n"
            ."R1,K-1,kepuasan,2027-01-10,,5,4,Bagus,Budi,budi@x.com,0812\n"
            ."R2,K-1,trainer,2027-01-10,2,,,Kurang jelas,Ani,,\n"
            ."R3,K-1,lain,2027-01-10,4,,,,,\n";
        $imp = app(LmsImporter::class);
        $r = $imp->importSurveys($this->csv($csv), null);
        $this->assertSame(2, $r->created_count);
        $this->assertSame(1, $r->skipped_count);
        $first = SurveyResponse::firstWhere('external_id', 'csv:R1');
        $this->assertSame('kepuasan_peserta', $first->survey_type);
        $this->assertEquals(4.5, $first->score, 'Skor dihitung dari rata-rata aspek');
        $this->assertNotNull($first->training_class_id);
        $this->assertTrue(SurveyResponse::firstWhere('external_id', 'csv:R2')->is_negative);
        // Tidak ada kolom data pribadi di tabel
        foreach (['nama', 'email', 'no_hp', 'name', 'phone'] as $col) {
            $this->assertFalse(Schema::hasColumn('survey_responses', $col));
        }
        $this->assertStringNotContainsString('Budi', json_encode(SurveyResponse::all()->toArray()));
        // Impor ulang berkas yang sama tidak menggandakan
        $r2 = $imp->importSurveys($this->csv($csv), null);
        $this->assertSame(0, $r2->created_count);
        $this->assertSame(2, SurveyResponse::count());
    }

    public function test_sumber_api_memakai_kontrak_yang_sama(): void
    {
        $this->seedBase();
        config(['smm.lms.api_url' => 'https://lms.contoh.test/api/smm', 'smm.lms.api_token' => 'rahasia']);
        Http::fake([
            'lms.contoh.test/api/smm/kelas*' => Http::response(['data' => [['kode_batch' => 'API-1', 'nama_pelatihan' => 'Ahli K3', 'jumlah_peserta' => 22, 'lulus' => 20]]]),
            'lms.contoh.test/api/smm/survei*' => Http::response(['data' => [['id_respons' => 9, 'kode_kelas' => 'API-1', 'jenis_survei' => 'kepuasan_peserta', 'skor' => 4.8]]]),
            'lms.contoh.test/api/smm/keluhan*' => Http::response(['data' => []]),
        ]);
        $this->assertTrue(ApiLmsSource::configured());
        $imp = app(LmsImporter::class);
        $imp->importClasses(ApiLmsSource::fromConfig('2027-01-01'), null, 'api');
        $imp->importSurveys(ApiLmsSource::fromConfig('2027-01-01'), null, 'api');
        $this->assertSame('api', TrainingClass::firstWhere('class_code', 'API-1')->source);
        $this->assertSame('api:9', SurveyResponse::first()->external_id);
        Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer rahasia') && str_contains($req->url(), 'sejak=2027-01-01'));
        $this->artisan('smm:lms-sync')->assertSuccessful();
    }

    public function test_api_lms_asli_kelas_survei_dan_keluhan_tanpa_data_pribadi(): void
    {
        $this->seedBase();
        \Illuminate\Support\Facades\Notification::fake();
        $mr = $this->makeUser('mr');
        config(['smm.lms.api_url' => 'https://lms.contoh.test/api/smm', 'smm.lms.api_token' => 'rahasia']);
        // Bentuk jawaban persis seperti API LMS Artha Safety (docs/API-SMM.md di repo LMS)
        Http::fake([
            'lms.contoh.test/api/smm/kelas*' => Http::response(['data' => [['kode_batch' => 'B42', 'nama_pelatihan' => 'Ahli K3 Umum', 'tgl_mulai' => '2026-11-02',
                'tgl_selesai' => '2026-11-13', 'jumlah_peserta' => 25, 'hadir' => 24, 'lulus' => null, 'rata_nilai' => null, 'trainer' => ['Hendra Gunawan', 'Agus Setiawan']]]]),
            'lms.contoh.test/api/smm/survei*' => Http::response(['data' => [['id_respons' => 'SRV-261106-AA5FE', 'kode_batch' => 'B42', 'jenis_survei' => 'evaluasi_trainer',
                'tanggal' => '2026-11-13', 'skor' => 4.25, 'aspek_penguasaan' => 5, 'aspek_penyampaian' => 4, 'aspek_interaksi' => 4, 'aspek_waktu' => 4, 'trainer' => 'Hendra Gunawan', 'komentar' => 'Bagus']]]),
            'lms.contoh.test/api/smm/keluhan*' => Http::response(['data' => [
                ['id_keluhan' => 'KLH-261106-4750B', 'kode_batch' => 'B42', 'tanggal' => '2026-11-10', 'kategori' => 'fasilitas', 'uraian' => 'AC ruang kelas mati'],
                ['id_keluhan' => 'KLH-261107-11111', 'kode_batch' => null, 'tanggal' => '2026-11-11', 'kategori' => 'dokumen', 'uraian' => 'Sertifikat belum terbit'],
                ['id_keluhan' => 'KLH-RUSAK', 'kode_batch' => null, 'tanggal' => '2026-11-11', 'kategori' => 'lainnya', 'uraian' => ''],
            ]]),
        ]);
        $this->artisan('smm:lms-sync')->assertSuccessful();
        $this->artisan('smm:lms-sync')->assertSuccessful(); // tarikan kedua tidak membuat data ganda

        $class = TrainingClass::firstWhere('class_code', 'B42');
        $this->assertSame('Hendra Gunawan, Agus Setiawan', $class->trainers);
        $this->assertSame(1, SurveyResponse::count());
        $this->assertSame('Hendra Gunawan', SurveyResponse::first()->subject);

        $this->assertSame(2, \App\Models\Complaint::count());
        $c = \App\Models\Complaint::firstWhere('external_id', 'lms:KLH-261106-4750B');
        $this->assertSame(['keluhan', 'lms', 'sarana', 'B42', 'baru'], [$c->type, $c->channel, $c->category, $c->class_code, $c->status]);
        $this->assertNull($c->reporter_name);
        $this->assertNull($c->reporter_contact);
        $this->assertNull($c->created_by);
        $this->assertSame('2026-11-13', $c->response_due_date->toDateString());
        $this->assertStringContainsString('KLH-261106-4750B', $c->description);
        $this->assertSame('sertifikat', \App\Models\Complaint::firstWhere('external_id', 'lms:KLH-261107-11111')->category);
        $last = \App\Models\LmsImport::where('kind', 'keluhan')->latest('id')->first();
        $this->assertSame([0, 3], [$last->created_count, $last->skipped_count], 'Tarikan kedua: 2 sudah ada + 1 rusak');
        \Illuminate\Support\Facades\Notification::assertSentTo($mr, \App\Notifications\SmmNotification::class);

        $this->actingAs($mr)->get(route('complaints.show', $c))->assertOk()->assertSee('Sistem (dari LMS)');
        $this->actingAs($mr)->get(route('complaints.index'))->assertOk();
    }

    public function test_halaman_impor_dan_template(): void
    {
        $this->seedBase();
        $gm = $this->makeUser('gm');
        $this->actingAs($gm)->get(route('classes.import'))->assertOk();
        $this->actingAs($gm)->get(route('classes.template', 'kelas'))->assertOk();
        $file = UploadedFile::fake()->createWithContent('kelas.csv', "kode_kelas,program\nW-1,Ahli K3\n");
        $this->actingAs($gm)->post(route('classes.import.run'), ['kind' => 'kelas', 'file' => $file])->assertRedirect(route('classes.import'));
        $this->assertTrue(TrainingClass::where('class_code', 'W-1')->exists());
        $this->actingAs($this->makeUser('staf', 'CS'))->get(route('classes.import'))->assertForbidden();
    }
}
