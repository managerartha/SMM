<?php

namespace Tests\Feature;

use App\Domain\Documents\DocumentWorkflow;
use App\Domain\Facilities\FacilityService;
use App\Domain\Kpi\KpiCalculator;
use App\Domain\Kpi\KpiService;
use App\Domain\Notifications\Reminders;
use App\Domain\Personnel\PersonnelService;
use App\Domain\Records\RecordService;
use App\Domain\Suppliers\SupplierService;
use App\Domain\Surveys\SurveyService;
use App\Models\Division;
use App\Models\DocumentType;
use App\Models\KpiValue;
use App\Models\SurveyResponse;
use App\Models\TrainingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase3Test extends TestCase
{
    use RefreshDatabase;

    private User $gm, $mr, $kadiv, $staf, $stafCs, $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seedBase();
        $this->gm = $this->makeUser('gm');
        $this->mr = $this->makeUser('mr');
        $this->kadiv = $this->makeUser('kadiv', 'OPS');
        $this->staf = $this->makeUser('staf', 'OPS');
        $this->stafCs = $this->makeUser('staf', 'CS');
        $this->auditor = $this->makeUser('auditor', null, ['access_expires_at' => now()->addMonth()]);
    }

    private function div(string $c): int
    {
        return Division::where('code', $c)->value('id');
    }

    private function effectiveForm()
    {
        $wf = app(DocumentWorkflow::class);
        $doc = $wf->create($this->mr, ['document_type_id' => DocumentType::where('code', 'F')->value('id'), 'division_id' => $this->div('OPS'), 'title' => 'Daftar Hadir', 'access_scope' => 'umum'], $this->pdf());
        $wf->submit($this->mr, $doc->revisions()->first(), null);
        $wf->approve($this->gm, $doc->revisions()->first()->fresh());

        return $doc->fresh();
    }

    public function test_nomor_catatan_mutu_per_formulir_dan_tahun(): void
    {
        $form = $this->effectiveForm(); // ASI-F-OPS-01
        $rs = app(RecordService::class);
        $base = ['division_id' => $this->div('OPS'), 'title' => 'Daftar hadir'];
        $this->assertSame('F-OPS-01/2027/0001', $rs->create($this->staf, $base + ['document_id' => $form->id, 'activity_date' => '2027-01-10'], [$this->pdf()])->number);
        $this->assertSame('F-OPS-01/2027/0002', $rs->create($this->staf, $base + ['document_id' => $form->id, 'activity_date' => '2027-03-10'], [$this->pdf()])->number);
        $this->assertSame('CM/2027/0001', $rs->create($this->staf, $base + ['activity_date' => '2027-03-11'], [$this->pdf()])->number);
        $r = $rs->create($this->staf, $base + ['document_id' => $form->id, 'activity_date' => '2028-01-02', 'retention_years' => 3], [$this->pdf()]);
        $this->assertSame('F-OPS-01/2028/0001', $r->number, 'Urutan kembali ke 1 di tahun baru');
        $this->assertSame('2031-01-02', $r->retention_until->toDateString());
        $this->assertSame(8, $r->evidence_category_id);
    }

    public function test_catatan_mutu_wajib_berkas_dan_divisi_sendiri_dan_visibilitas(): void
    {
        $rs = app(RecordService::class);
        try {
            $rs->create($this->staf, ['division_id' => $this->div('OPS'), 'title' => 'x', 'activity_date' => now()->toDateString()], []);
            $this->fail('Wajib berkas');
        } catch (ValidationException) {
        }
        try {
            $rs->create($this->staf, ['division_id' => $this->div('CS'), 'title' => 'x', 'activity_date' => now()->toDateString()], [$this->pdf()]);
            $this->fail('Divisi lain');
        } catch (ValidationException) {
        }
        $r = $rs->create($this->staf, ['division_id' => $this->div('OPS'), 'title' => 'Foto praktik', 'activity_date' => now()->toDateString()], [$this->pdf()]);
        $this->actingAs($this->stafCs)->get(route('records.show', $r))->assertForbidden();
        $this->actingAs($this->kadiv)->get(route('records.show', $r))->assertOk();
        $this->actingAs($this->auditor)->get(route('records.show', $r))->assertOk();
        $file = $r->attachments()->first();
        $this->actingAs($this->stafCs)->get(route('attachments.download', $file->uuid))->assertForbidden();
        $this->actingAs($this->auditor)->get(route('attachments.download', $file->uuid))->assertOk();
        $this->assertStringStartsWith('Catatan Mutu/', $file->drive_folder_path);
    }

    public function test_personel_nomor_berkas_diperbarui_dan_pengingat(): void
    {
        $ps = app(PersonnelService::class);
        $ta = $ps->create($this->gm, ['type' => 'tenaga_ahli', 'name' => 'A', 'status' => 'aktif']);
        $tt = $ps->create($this->gm, ['type' => 'tenaga_teknis', 'name' => 'B', 'status' => 'aktif']);
        $this->assertSame('TA-001', $ta->number);
        $this->assertSame('TT-001', $tt->number);
        $this->assertSame('TA-002', $ps->create($this->gm, ['type' => 'tenaga_ahli', 'name' => 'C', 'status' => 'aktif'])->number);

        $old = $ps->addDocument($this->gm, $ta, ['category' => 'lisensi', 'title' => 'SKP', 'expires_at' => now()->addDays(100)->toDateString()], $this->pdf());
        $r = app(Reminders::class);
        $this->assertSame(0, $r->personnelDocuments());
        $this->travel(11)->days();
        $this->assertSame(1, $r->personnelDocuments(), 'H-89 → pengingat 90 hari');
        $this->assertSame(0, $r->personnelDocuments());

        $new = $ps->addDocument($this->gm, $ta, ['category' => 'lisensi', 'title' => 'SKP (perpanjangan)', 'expires_at' => now()->addYears(3)->toDateString()], $this->pdf(), $old);
        $this->assertSame($new->id, $old->fresh()->replaced_by_id);
        $this->assertCount(1, $ta->activeDocuments()->get());
        $this->travel(60)->days();
        $this->assertSame(0, $r->personnelDocuments(), 'Berkas lama yang sudah diganti tidak diingatkan');

        $this->actingAs($this->stafCs)->get(route('personnel.index'))->assertForbidden();
        $auditor = $this->makeUser('auditor', null, ['access_expires_at' => now()->addMonth()]); // auditor lama sudah lewat masa akun
        $this->actingAs($auditor)->get(route('personnel.show', $ta))->assertOk();
        $this->actingAs($auditor)->get(route('personnel.documents.download', [$ta, $new]))->assertOk();
        $this->actingAs($auditor)->post(route('personnel.documents.verify', [$ta, $new]))->assertForbidden();
    }

    public function test_sarana_pemeriksaan_kalibrasi_dan_pengingat(): void
    {
        $fs = app(FacilityService::class);
        $f = $fs->create($this->gm, ['category' => 'ALT', 'name' => 'APAR', 'quantity' => 2, 'condition' => 'baik', 'inspection_interval_months' => 6, 'status' => 'aktif',
            'needs_calibration' => true, 'next_calibration_at' => now()->addDays(30)->toDateString()]);
        $this->assertSame('SP-ALT-001', $f->number);
        $this->assertTrue($f->next_inspection_at->isSameDay(now()->addMonths(6)));
        $this->assertSame('SP-ELK-001', $fs->create($this->gm, ['category' => 'ELK', 'name' => 'Proyektor', 'quantity' => 1, 'condition' => 'baik', 'status' => 'aktif'])->number);

        $fs->addLog($this->gm, $f, ['type' => 'perbaikan', 'performed_at' => now()->toDateString(), 'result' => 'Ganti selang', 'condition_after' => 'rusak_ringan']);
        $this->assertSame('rusak_ringan', $f->fresh()->condition);
        $fs->addLog($this->gm, $f->fresh(), ['type' => 'kalibrasi', 'performed_at' => now()->toDateString(), 'result' => 'OK', 'valid_until' => now()->addYear()->toDateString()]);
        $this->assertTrue($f->fresh()->next_calibration_at->isSameDay(now()->addYear()));

        $r = app(Reminders::class);
        $this->assertSame(0, $r->facilities());
        $this->travelTo(now()->addMonths(6)->subDays(10));
        $this->assertSame(1, $r->facilities(), 'Pemeriksaan berkala dalam 14 hari');
        $this->assertSame(0, $r->facilities());
        $this->actingAs($this->staf)->get(route('facilities.show', $f))->assertOk();
        $this->actingAs($this->staf)->post(route('facilities.logs.store', $f), ['type' => 'pemeriksaan', 'performed_at' => now()->toDateString(), 'result' => 'x'])->assertForbidden();
    }

    public function test_kpi_otomatis_manual_dan_pencapaian(): void
    {
        $this->travelTo('2027-02-20 10:00:00');
        TrainingClass::create(['class_code' => 'A', 'program_name' => 'X', 'end_date' => '2027-02-05', 'participants' => 20, 'attended' => 19, 'passed' => 18]);
        TrainingClass::create(['class_code' => 'B', 'program_name' => 'X', 'end_date' => '2027-02-15', 'participants' => 10, 'attended' => 10, 'passed' => 9]);
        TrainingClass::create(['class_code' => 'C', 'program_name' => 'X', 'end_date' => '2027-03-01', 'participants' => 10, 'passed' => 0]);
        foreach ([5, 4, 3] as $s) {
            SurveyResponse::create(['survey_type' => 'kepuasan_peserta', 'responded_at' => '2027-02-10', 'score' => $s]);
        }
        $calc = app(KpiCalculator::class);
        $this->assertEquals(90.0, $calc->value('class_pass_rate', '2027-02'));
        $this->assertEquals(96.67, $calc->value('class_attendance_rate', '2027-02'));
        $this->assertEquals(2.0, $calc->value('class_count', '2027-02'));
        $this->assertEquals(4.0, $calc->value('survey_avg_peserta', '2027-02'));
        $this->assertEquals(66.67, $calc->value('survey_satisfied_pct', '2027-02'));
        $this->assertNull($calc->value('survey_avg_klien', '2027-02'), 'Tanpa data → dilewati');

        $ks = app(KpiService::class);
        $pass = $ks->create($this->gm, ['name' => 'Kelulusan', 'unit' => '%', 'target' => 92, 'comparator' => '>=', 'source' => 'class_pass_rate', 'year' => 2027, 'division_id' => $this->div('OPS')]);
        $this->assertSame('KPI-01', $pass->code);
        $this->assertSame(1, $calc->run('2027-02'));
        $this->assertFalse($pass->achieved(KpiValue::where('kpi_id', $pass->id)->value('value')));
        // Nilai manual tidak ditimpa perhitungan otomatis
        $ks->setValue($this->kadiv, $pass, '2027-02', 93, 'Koreksi data');
        $calc->run('2027-02');
        $this->assertEquals(93.0, KpiValue::where('kpi_id', $pass->id)->value('value'));
        $this->assertTrue($pass->achieved(93));

        $resp = $ks->create($this->gm, ['name' => 'Respons CS', 'unit' => 'menit', 'target' => 15, 'comparator' => '<=', 'source' => 'manual', 'year' => 2027, 'division_id' => $this->div('CS')]);
        $this->assertTrue($resp->achieved(12));
        $this->assertFalse($resp->achieved(20));
        // Kadiv OPS tidak boleh mengisi KPI divisi CS; periode harus di tahun KPI
        $this->actingAs($this->kadiv)->post(route('kpis.values.store', $resp), ['period' => '2027-02', 'value' => 10])->assertForbidden();
        $this->expectException(ValidationException::class);
        $ks->setValue($this->gm, $resp, '2026-12', 10, null);
    }

    public function test_survei_negatif_jadi_keluhan_dan_evaluasi_pemasok(): void
    {
        $r = SurveyResponse::create(['survey_type' => 'evaluasi_trainer', 'class_code' => 'K-9', 'responded_at' => now()->toDateString(), 'score' => 1.5, 'is_negative' => true, 'comment' => 'Trainer terlambat', 'subject' => 'Pak X']);
        $c = app(SurveyService::class)->toComplaint($this->mr, $r);
        $this->assertSame('survei', $c->source);
        $this->assertSame('K-9', $c->class_code);
        $this->assertSame('trainer', $c->category);
        $this->assertSame('tinggi', $c->severity);
        $this->assertSame($c->id, $r->fresh()->complaint_id);

        $ss = app(SupplierService::class);
        $s = $ss->create($this->gm, ['name' => 'Katering A', 'category' => 'katering', 'status' => 'aktif']);
        $this->assertSame('PMS-001', $s->number);
        $e = $ss->evaluate($this->gm, $s, ['period' => '2027-S1', 'quality' => 2, 'timeliness' => 3, 'price' => 4, 'service' => 2, 'decision' => 'dibina']);
        $this->assertEquals(2.75, $e->average);
        $this->assertSame('dievaluasi', $s->fresh()->status);
    }

    public function test_halaman_tahap3_dirender_ekspor_dan_hak_akses(): void
    {
        $this->seed(\Database\Seeders\DemoDataSeeder::class);
        $this->seed(\Database\Seeders\DemoPhase2Seeder::class);
        $this->seed(\Database\Seeders\DemoPhase3Seeder::class);
        $gm = User::where('email', 'gm@contoh.test')->first();
        $class = TrainingClass::first();
        foreach (['classes.index', 'records.index', 'records.create', 'personnel.index', 'personnel.create', 'facilities.index', 'facilities.create',
            'surveys.index', 'kpis.index', 'kpis.create', 'suppliers.index', 'suppliers.create', 'admin.programs.index', 'dashboard'] as $r) {
            $this->actingAs($gm)->get(route($r))->assertOk();
        }
        foreach ([route('classes.show', $class), route('personnel.show', \App\Models\Personnel::first()), route('facilities.show', \App\Models\Facility::first()),
            route('kpis.show', \App\Models\Kpi::first()), route('suppliers.show', \App\Models\Supplier::first()), route('records.show', \App\Models\QualityRecord::first()), route('search', ['q' => 'AK3U'])] as $url) {
            $this->assertSame(200, $this->actingAs($gm)->get($url)->getStatusCode(), $url);
        }
        foreach (['classes.export', 'records.export', 'personnel.export', 'facilities.export', 'surveys.export', 'kpis.export', 'suppliers.export'] as $e) {
            $this->actingAs($gm)->get(route($e))->assertOk()->assertHeader('content-disposition');
        }
        $staf = User::where('email', 'cs@contoh.test')->first();
        foreach (['personnel.index', 'surveys.index', 'kpis.index', 'suppliers.index'] as $r) {
            $this->actingAs($staf)->get(route($r))->assertForbidden();
        }
        $this->actingAs($staf)->get(route('classes.index'))->assertOk();
    }
}
