<?php

namespace Tests\Feature;

use App\Domain\Complaints\ComplaintService;
use App\Domain\Notifications\Reminders;
use App\Domain\Risks\RiskLevel;
use App\Domain\Risks\RiskService;
use App\Models\Complaint;
use App\Models\Division;
use App\Models\Ncr;
use App\Models\Risk;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ComplaintRiskTest extends TestCase
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

    private function complaint(User $by, string $type = 'keluhan', string $div = 'OPS'): Complaint
    {
        return app(ComplaintService::class)->create($by, [
            'type' => $type, 'source' => 'peserta', 'received_at' => now()->toDateString(), 'subject' => 'Ruang kelas panas',
            'description' => 'AC mati', 'division_id' => Division::where('code', $div)->value('id'), 'class_code' => 'AK3U-01',
        ]);
    }

    public function test_nomor_keluhan_dan_batas_tanggapan(): void
    {
        $this->travelTo('2027-05-10 10:00:00');
        $c = $this->complaint($this->stafCs);
        $this->assertSame('KLH/2027/001', $c->number);
        $this->assertSame('2027-05-13', $c->response_due_date->toDateString());
        $this->assertNull($this->complaint($this->stafCs, 'feedback')->response_due_date, 'Feedback tidak punya batas tanggapan');
        $this->travelTo('2028-01-02 08:00:00');
        $this->assertSame('KLH/2028/001', $this->complaint($this->stafCs)->number);
    }

    public function test_alur_tugaskan_tanggapi_dan_jadikan_ncr(): void
    {
        $svc = app(ComplaintService::class);
        $c = $this->complaint($this->stafCs);
        $svc->assign($this->kadiv, $c, $this->staf->id); // kadiv OPS menangani keluhan divisi OPS
        $this->assertSame('diproses', $c->fresh()->status);

        try {
            $svc->respond($this->staf, $c->fresh(), '   ', null);
            $this->fail('Tanggapan wajib');
        } catch (ValidationException) {
        }
        $svc->respond($this->staf, $c->fresh(), 'AC sudah diperbaiki', 'Teknisi dipanggil');
        $this->assertSame('selesai', $c->fresh()->status);

        $ncr = $svc->escalate($this->mr, $c->fresh(), ['title' => 'Perawatan AC', 'division_id' => Division::where('code', 'OPS')->value('id'), 'pic_id' => $this->kadiv->id, 'severity' => 'minor', 'target_date' => now()->addDays(20)->toDateString()]);
        $this->assertSame('keluhan', $ncr->source);
        $this->assertSame($ncr->id, $c->fresh()->ncr_id);
        $this->assertSame(Ncr::OPEN, $ncr->status);
        $this->assertSame($c->id, $ncr->complaint_id);
    }

    public function test_visibilitas_keluhan(): void
    {
        $mine = $this->complaint($this->stafCs, 'saran', 'CS');
        $other = $this->complaint($this->staf, 'keluhan', 'OPS');

        $this->actingAs($this->stafCs)->get(route('complaints.show', $mine))->assertOk();
        $this->actingAs($this->stafCs)->get(route('complaints.show', $other))->assertForbidden();
        $this->actingAs($this->stafCs)->get(route('complaints.index'))->assertSee($mine->number)->assertDontSee($other->number);
        $this->actingAs($this->kadiv)->get(route('complaints.show', $other))->assertOk();
        $this->actingAs($this->auditor)->get(route('complaints.show', $other))->assertOk();

        // Auditor hanya membaca: tidak bisa mencatat
        $this->expectException(AuthorizationException::class);
        $this->complaint($this->auditor);
    }

    public function test_staf_tidak_bisa_menugaskan_atau_membatalkan(): void
    {
        $c = $this->complaint($this->stafCs, 'keluhan', 'CS');
        $this->actingAs($this->stafCs)->post(route('complaints.assign', $c), ['pic_id' => $this->stafCs->id])->assertForbidden();
        $this->actingAs($this->stafCs)->post(route('complaints.cancel', $c), ['note' => 'x'])->assertForbidden();
    }

    public function test_pengingat_keluhan_lewat_batas(): void
    {
        $this->complaint($this->stafCs);
        $r = app(Reminders::class);
        $this->assertSame(0, $r->complaints());
        $this->travel(4)->days();
        $this->assertSame(1, $r->complaints());
        $this->assertSame(0, $r->complaints());
    }

    public function test_skor_dan_level_risiko_sesuai_batas(): void
    {
        $this->assertSame(RiskLevel::Low, RiskLevel::fromScore(1));
        $this->assertSame(RiskLevel::Low, RiskLevel::fromScore(5));
        $this->assertSame(RiskLevel::Medium, RiskLevel::fromScore(6));
        $this->assertSame(RiskLevel::Medium, RiskLevel::fromScore(11));
        $this->assertSame(RiskLevel::High, RiskLevel::fromScore(12));
        $this->assertSame(RiskLevel::High, RiskLevel::fromScore(25));
        $this->assertSame(20, RiskLevel::score(4, 5));
        $this->expectException(\InvalidArgumentException::class);
        RiskLevel::score(6, 1);
    }

    public function test_register_risiko_penilaian_ulang_dan_hak_akses(): void
    {
        $rs = app(RiskService::class);
        $r = $rs->create($this->kadiv, ['type' => 'risiko', 'division_id' => Division::where('code', 'OPS')->value('id'), 'title' => 'Trainer berhalangan', 'likelihood' => 3, 'impact' => 4, 'owner_id' => $this->kadiv->id]);
        $this->assertSame('RP-001', $r->number);
        $this->assertSame(12, $r->score);
        $this->assertSame('tinggi', $r->level);
        $this->assertTrue($r->review_date->isSameDay(now()->addYear()));

        $rs->reassess($this->kadiv, $r, 2, 4, 'Trainer cadangan tersedia');
        $r->refresh();
        $this->assertSame(8, $r->score);
        $this->assertSame('sedang', $r->level);
        $this->assertSame(2, $r->assessments()->count());

        // Kadiv tidak boleh mengelola risiko divisi lain
        try {
            $rs->create($this->kadiv, ['type' => 'peluang', 'division_id' => Division::where('code', 'MKT')->value('id'), 'title' => 'x', 'likelihood' => 1, 'impact' => 1, 'owner_id' => $this->kadiv->id]);
            $this->fail('Divisi lain');
        } catch (ValidationException) {
        }
        // Staf tidak punya akses register risiko
        $this->actingAs($this->staf)->get(route('risks.index'))->assertForbidden();
        $this->actingAs($this->auditor)->get(route('risks.show', $r))->assertOk();
        $this->actingAs($this->auditor)->post(route('risks.reassess', $r), ['likelihood' => 1, 'impact' => 1, 'note' => 'x'])->assertForbidden();
    }

    public function test_halaman_tahap2_dirender_dan_ekspor(): void
    {
        $c = $this->complaint($this->stafCs);
        $rs = app(RiskService::class);
        $r = $rs->create($this->gm, ['type' => 'risiko', 'division_id' => Division::where('code', 'QA')->value('id'), 'title' => 'SKP kedaluwarsa', 'likelihood' => 2, 'impact' => 5, 'owner_id' => $this->gm->id]);
        foreach ([route('complaints.index'), route('complaints.create'), route('complaints.show', $c), route('complaints.edit', $c),
            route('ncrs.index'), route('ncrs.create'), route('risks.index'), route('risks.create'), route('risks.show', $r), route('risks.edit', $r),
            route('dashboard'), route('search', ['q' => 'kelas'])] as $url) {
            $this->actingAs($this->gm)->get($url)->assertOk();
        }
        foreach (['complaints.export', 'ncrs.export', 'risks.export'] as $e) {
            $this->actingAs($this->gm)->get(route($e))->assertOk()->assertHeader('content-disposition');
        }
        $this->actingAs($this->gm)->get(route('search', ['q' => 'AK3U-01']))->assertSee($c->number);
    }

    public function test_lampiran_hanya_untuk_yang_berhak(): void
    {
        $c = app(ComplaintService::class)->create($this->stafCs, [
            'type' => 'keluhan', 'source' => 'peserta', 'received_at' => now()->toDateString(), 'subject' => 'x', 'description' => 'y',
            'division_id' => Division::where('code', 'CS')->value('id'),
        ], [$this->pdf('bukti')]);
        $file = $c->attachments()->first();
        $this->actingAs($this->stafCs)->get(route('attachments.download', $file->uuid))->assertOk();
        $this->actingAs($this->staf)->get(route('attachments.download', $file->uuid))->assertForbidden();
        $this->actingAs($this->auditor)->get(route('attachments.download', $file->uuid))->assertOk();
        $this->assertStringStartsWith('Keluhan/KLH-', $file->drive_folder_path);
    }

    public function test_izin_baru_ditambahkan_ke_peran_lama_saat_pembaruan(): void
    {
        // Simulasi instalasi lama: izin keluhan.catat belum ada & staf belum memilikinya
        $perm = \Spatie\Permission\Models\Permission::where('name', 'keluhan.catat')->first();
        $perm->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $staf = \Spatie\Permission\Models\Role::findByName('staf');
        $staf->givePermissionTo([]); // izin lama yang disesuaikan GM tetap
        $this->seedBase();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertTrue($staf->fresh()->hasPermissionTo('keluhan.catat'));
    }
}
