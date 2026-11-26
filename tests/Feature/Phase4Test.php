<?php

namespace Tests\Feature;

use App\Domain\Audits\AuditService;
use App\Domain\Files\FileStore;
use App\Domain\Innovations\InnovationService;
use App\Domain\Reports\PerformanceReportService;
use App\Domain\Reviews\ManagementReviewService;
use App\Models\Audit;
use App\Models\AuditProgram;
use App\Models\Division;
use App\Models\EvidencePackage;
use App\Models\Innovation;
use App\Models\Ncr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase4Test extends TestCase
{
    use RefreshDatabase;

    private User $gm, $dir, $mr, $kadiv, $staf, $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seedBase();
        $this->gm = $this->makeUser('gm');
        $this->dir = $this->makeUser('direktur');
        $this->mr = $this->makeUser('mr');
        $this->kadiv = $this->makeUser('kadiv', 'OPS');
        $this->staf = $this->makeUser('staf', 'OPS');
        $this->auditor = $this->makeUser('auditor', null, ['access_expires_at' => now()->addMonths(3)]);
        Division::where('code', 'OPS')->update(['head_user_id' => $this->kadiv->id]);
    }

    private function div(string $c): int
    {
        return Division::where('code', $c)->value('id');
    }

    private function program(): AuditProgram
    {
        return AuditProgram::first() ?? app(AuditService::class)->saveProgram($this->mr, ['year' => (int) now()->format('Y'), 'objective' => 'Tujuan', 'scope' => 'Semua', 'criteria' => 'ISO 9001']);
    }

    private function scheduled(?AuditProgram $p = null, ?User $auditor = null, string $div = 'OPS'): Audit
    {
        return app(AuditService::class)->schedule($this->mr, $p ?? $this->program(), ['division_id' => $this->div($div), 'process' => 'Pelatihan',
            'planned_date' => now()->toDateString(), 'auditor_id' => ($auditor ?? $this->auditor)->id]);
    }

    private function expectFail(callable $fn, string $class = ValidationException::class): void
    {
        try {
            $fn();
            $this->fail('Seharusnya ditolak');
        } catch (\Throwable $e) {
            $this->assertInstanceOf($class, $e, $e->getMessage());
        }
    }

    public function test_program_audit_disahkan_direktur_dan_pdf_tersimpan(): void
    {
        $svc = app(AuditService::class);
        $p = $this->program();
        $this->expectFail(fn () => $svc->submitProgram($this->mr, $p), ValidationException::class); // belum ada audit
        $this->scheduled($p);
        $svc->submitProgram($this->mr, $p->fresh());
        $this->expectFail(fn () => $svc->decideProgram($this->gm, $p->fresh(), true, null), HttpException::class); // pengesah = Direktur
        $svc->decideProgram($this->dir, $p->fresh(), true, null);
        $p->refresh();
        $this->assertSame('disahkan', $p->status);
        $this->assertSame($this->dir->name, $p->approved_by_name);
        $this->assertNotNull($p->file_id);
        $this->assertStringStartsWith('%PDF', file_get_contents(app(FileStore::class)->toTemp($p->file)));

        // Menambah jadwal setelah disahkan → perlu disahkan ulang
        $this->scheduled($p);
        $this->assertSame('draf', $p->fresh()->status);
    }

    public function test_auditor_tidak_boleh_mengaudit_divisinya_sendiri(): void
    {
        $this->expectFail(fn () => $this->scheduled(null, $this->kadiv, 'OPS'));
        $this->expectFail(fn () => $this->scheduled(null, $this->staf, 'CS')); // staf tidak punya izin audit.laksana
        $a = $this->scheduled(null, $this->kadiv, 'CS');
        $this->assertSame($this->kadiv->id, $a->auditor_id);
        $this->assertSame(10, $a->items()->count(), 'Checklist bawaan');
    }

    public function test_temuan_menjadi_ncr_dan_laporan_disahkan_gm_bukan_auditor(): void
    {
        $svc = app(AuditService::class);
        $a = $this->scheduled();
        $this->expectFail(fn () => $svc->addFinding($this->staf, $a, ['type' => 'minor', 'clause' => '7.5', 'description' => 'x']), HttpException::class);

        $f = $svc->addFinding($this->auditor, $a, ['type' => 'minor', 'clause' => '7.5', 'description' => 'Daftar hadir tidak lengkap']);
        $obs = $svc->addFinding($this->auditor, $a, ['type' => 'observasi', 'clause' => null, 'description' => 'Catatan']);
        $this->assertNull($obs->ncr_id);
        $ncr = Ncr::findOrFail($f->ncr_id);
        $this->assertSame('audit_internal', $ncr->source);
        $this->assertSame($this->kadiv->id, $ncr->pic_id, 'PIC = auditi/kepala divisi');
        $this->assertSame(now()->addDays(60)->toDateString(), $ncr->target_date->toDateString());
        $this->assertSame('pelaksanaan', $a->fresh()->status);

        $this->expectFail(fn () => $svc->submitReport($this->auditor, $a->fresh(), 'Kesimpulan')); // checklist belum lengkap
        $svc->saveChecklist($this->auditor, $a, $a->items->mapWithKeys(fn ($i) => [$i->id => ['result' => 'sesuai', 'evidence' => 'ok']])->all(), null, null);
        $svc->submitReport($this->auditor, $a->fresh(), 'Sesuai dan efektif');
        $this->expectFail(fn () => $svc->decideReport($this->dir, $a->fresh(), true, null), HttpException::class); // pengesah bawaan = GM
        $svc->decideReport($this->gm, $a->fresh(), true, null);
        $a->refresh();
        $this->assertSame('selesai', $a->status);
        $this->assertNotNull($a->file_id);

        // HTTP: halaman & unduhan
        $this->actingAs($this->auditor)->get(route('audits.show', $a))->assertOk()->assertSee($ncr->number);
        $this->actingAs($this->auditor)->get(route('attachments.download', $a->file->uuid))->assertOk();
        $this->actingAs($this->staf)->get(route('attachments.download', $a->file->uuid))->assertForbidden();
        $this->actingAs($this->staf)->get(route('audits.index'))->assertForbidden();
    }

    public function test_tinjauan_manajemen_masukan_otomatis_tindak_lanjut_dan_pengesahan(): void
    {
        $svc = app(ManagementReviewService::class);
        $m = $svc->create($this->mr, ['meeting_date' => now()->toDateString(), 'period_from' => now()->subYear()->toDateString(), 'period_to' => now()->toDateString()]);
        $this->assertMatchesRegularExpression('#^TM/\d{4}/01$#', $m->number);
        $this->assertNotEmpty($m->inputs['ketidaksesuaian_capa']);
        $act = $svc->addAction($this->mr, $m, ['description' => 'Tambah trainer', 'pic_id' => $this->kadiv->id, 'due_date' => now()->addMonth()->toDateString()]);

        $this->expectFail(fn () => $svc->submit($this->mr, $m->fresh())); // kesimpulan wajib
        $this->actingAs($this->mr)->put(route('reviews.update', $m), ['attendees' => 'Direktur, GM, MR', 'outputs' => ['kesimpulan' => 'SMM sesuai dan efektif'], 'inputs' => $m->inputs])->assertRedirect();
        $svc->submit($this->mr, $m->fresh());
        $this->expectFail(fn () => $svc->decide($this->gm, $m->fresh(), true, null), HttpException::class);
        $svc->decide($this->dir, $m->fresh(), true, null);
        $this->assertSame('disahkan', $m->fresh()->status);
        $this->assertNotNull($m->fresh()->file_id);

        $this->actingAs($this->kadiv)->get(route('dashboard'))->assertOk()->assertSee('Tindak lanjut TM');
        $this->actingAs($this->staf)->post(route('reviews.actions.complete', [$m, $act]), ['result' => 'x'])->assertForbidden();
        $this->actingAs($this->kadiv)->post(route('reviews.actions.complete', [$m, $act]), ['result' => 'Sudah rekrut'])->assertRedirect();
        $this->assertNotNull($act->fresh()->completed_at);
    }

    public function test_inovasi_disetujui_sesuai_anggaran_dan_selesai_wajib_bukti(): void
    {
        $svc = app(InnovationService::class);
        $base = ['background' => 'b', 'objective' => 'o', 'indicator' => 'i', 'pic_id' => $this->staf->id];
        $free = $svc->propose($this->staf, $base + ['title' => 'Tanpa anggaran', 'budget' => 0]);
        $paid = $svc->propose($this->staf, $base + ['title' => 'Dengan anggaran', 'budget' => 2000000]);
        $this->assertMatchesRegularExpression('#^INV/\d{4}/01$#', $free->number);

        $this->expectFail(fn () => $svc->decide($this->gm, $paid, true, null), HttpException::class);
        $this->expectFail(fn () => $svc->decide($this->dir, $paid, false, null)); // tolak wajib alasan
        $svc->decide($this->dir, $paid, true, null);
        $svc->decide($this->gm, $free, true, null);
        $this->assertSame('disetujui', $free->fresh()->status);

        $svc->start($this->staf, $free->fresh());
        $this->expectFail(fn () => $svc->complete($this->staf, $free->fresh(), ['result' => 'r', 'result_value' => '5', 'evaluation' => 'e']));
        $this->actingAs($this->staf)->post(route('innovations.evidence', $free), ['evidence' => [$this->pdf()]])->assertRedirect();
        $svc->complete($this->staf, $free->fresh(), ['result' => 'r', 'result_value' => '5', 'evaluation' => 'e']);
        $this->assertSame('selesai', $free->fresh()->status);

        $file = $free->fresh()->attachments->first();
        $this->actingAs($this->auditor)->get(route('attachments.download', $file->uuid))->assertOk();
        $this->actingAs($this->staf)->get(route('innovations.show', $free))->assertOk();
    }

    public function test_laporan_kinerja_nomor_dan_tidak_bisa_disusun_ulang_setelah_disahkan(): void
    {
        $svc = app(PerformanceReportService::class);
        $r = $svc->generate($this->mr, 'bulanan', '2026-05');
        $this->assertSame('LKL/2026/05', $r->number);
        $this->assertSame($r->id, $svc->generate($this->mr, 'bulanan', '2026-05')->id, 'Disusun ulang, bukan dobel');
        $this->assertSame('LKL/2026', $svc->generate($this->mr, 'tahunan', '2026')->number);

        $this->expectFail(fn () => $svc->submit($this->mr, $r->fresh())); // analisis wajib
        $svc->saveAnalysis($this->mr, $r->fresh(), 'Analisis');
        $svc->submit($this->mr, $r->fresh());
        $this->expectFail(fn () => $svc->decide($this->dir, $r->fresh(), true, null), HttpException::class); // bulanan = GM
        $svc->decide($this->gm, $r->fresh(), true, null);
        $this->assertNotNull($r->fresh()->file_id);
        $this->expectFail(fn () => $svc->generate($this->mr, 'bulanan', '2026-05'));

        $this->actingAs($this->auditor)->get(route('reports.show', $r))->assertOk()->assertSee('Analisis');
        $this->actingAs($this->staf)->get(route('reports.index'))->assertForbidden();
    }

    public function test_paket_bukti_berisi_16_folder_dan_daftar_isi(): void
    {
        $a = $this->scheduled();
        app(AuditService::class)->addFinding($this->auditor, $a, ['type' => 'mayor', 'clause' => '8.5', 'description' => 'Temuan']);
        app(InnovationService::class)->propose($this->staf, ['title' => 'Ide', 'background' => 'b', 'objective' => 'o', 'indicator' => 'i', 'budget' => 0]);

        $this->actingAs($this->auditor)->post(route('evidence.store'), ['date_from' => now()->subYear()->toDateString(), 'date_to' => now()->toDateString()])->assertForbidden();
        $this->actingAs($this->mr)->post(route('evidence.store'), ['date_from' => now()->subYear()->toDateString(), 'date_to' => now()->toDateString()])->assertRedirect();
        $pkg = EvidencePackage::firstOrFail();
        $this->assertSame('selesai', $pkg->status, (string) $pkg->error);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(app(FileStore::class)->toTemp($pkg->file)) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $this->assertContains('00_Daftar_Isi.pdf', $names);
        $tops = collect($names)->map(fn ($n) => explode('/', $n)[0])->filter(fn ($n) => preg_match('/^\d{2}_/', $n) && $n !== '00_Daftar_Isi.pdf')->unique();
        $this->assertCount(16, $tops, implode(', ', $tops->all()));
        $this->assertTrue(collect($names)->contains(fn ($n) => str_starts_with($n, '14_') && str_ends_with($n, '.pdf')), 'NCR tercetak di folder 14');

        $this->actingAs($this->auditor)->get(route('attachments.download', $pkg->file->uuid))->assertOk();
        $this->actingAs($this->staf)->get(route('attachments.download', $pkg->file->uuid))->assertForbidden();
        $this->actingAs($this->mr)->get(route('evidence.index'))->assertOk()->assertSee('Unduh ZIP');
    }

    public function test_semua_halaman_tahap4_tampil_per_peran(): void
    {
        $this->seed(\Database\Seeders\DemoDataSeeder::class);
        $this->seed(\Database\Seeders\DemoPhase2Seeder::class);
        $this->seed(\Database\Seeders\DemoPhase3Seeder::class);
        $this->seed(\Database\Seeders\DemoPhase4Seeder::class);
        $urls = [route('audits.index'), route('audits.programs.show', AuditProgram::first()), route('audits.show', Audit::first()), route('reviews.index'),
            route('reviews.show', \App\Models\ManagementReview::first()), route('innovations.index'), route('innovations.create'), route('innovations.show', Innovation::first()),
            route('innovations.edit', Innovation::where('status', 'usulan')->first()), route('reports.index'), route('reports.show', \App\Models\PerformanceReport::first()), route('evidence.index'), route('dashboard')];
        foreach (['gm@contoh.test', 'direktur@contoh.test', 'mr@contoh.test', 'auditor@contoh.test', 'kadiv.ops@contoh.test', 'cs@contoh.test'] as $email) {
            $u = User::where('email', $email)->first();
            foreach ($urls as $url) {
                $status = $this->actingAs($u)->get($url)->status();
                $this->assertContains($status, [200, 403], "{$email} {$url} → {$status}");
            }
        }
        $this->actingAs(User::where('email', 'gm@contoh.test')->first())->get(route('dashboard'))->assertSee('laporan audit');
        $this->actingAs(User::where('email', 'direktur@contoh.test')->first())->get(route('dashboard'))->assertSee('Setujui inovasi');
        $this->actingAs(User::where('email', 'auditor@contoh.test')->first())->get(route('dashboard'))->assertSee('Laksanakan audit');
    }
}
