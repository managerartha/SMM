<?php

namespace Tests\Feature;

use App\Domain\Capa\CapaWorkflow;
use App\Domain\Notifications\Reminders;
use App\Models\Division;
use App\Models\Ncr;
use App\Models\User;
use App\Notifications\SmmNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CapaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private CapaWorkflow $wf;
    private User $gm, $mr, $kadiv, $staf, $direktur;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seedBase();
        $this->wf = app(CapaWorkflow::class);
        $this->gm = $this->makeUser('gm');
        $this->mr = $this->makeUser('mr');
        $this->direktur = $this->makeUser('direktur');
        $this->kadiv = $this->makeUser('kadiv', 'OPS');
        $this->staf = $this->makeUser('staf', 'OPS');
    }

    private function ncr(?User $by = null): Ncr
    {
        return $this->wf->create($by ?? $this->mr, [
            'source' => 'temuan_internal', 'title' => 'Daftar hadir tidak lengkap', 'description' => 'Uraian',
            'division_id' => Division::where('code', 'OPS')->value('id'), 'severity' => 'minor',
            'found_at' => now()->toDateString(), 'pic_id' => $this->kadiv->id, 'target_date' => now()->addDays(14)->toDateString(),
        ]);
    }

    /** Sampai tahap pemantauan; kembalikan NCR. */
    private function toMonitoring(): Ncr
    {
        $n = $this->ncr();
        $this->wf->recordAnalysis($this->kadiv, $n, 'Koreksi segera', '5why', 'Akar masalah');
        $a = $this->wf->addAction($this->kadiv, $n->fresh(), 'Tindakan A', $this->staf->id, now()->addDays(7)->toDateString());
        $this->wf->startActions($this->kadiv, $n->fresh());
        $this->wf->completeAction($this->staf, $a->fresh(), 'Sudah dilakukan');
        $this->wf->verify($this->mr, $n->fresh(), 'Diperiksa, sesuai');

        return $n->fresh();
    }

    public function test_nomor_dan_alur_lengkap_sampai_ditutup(): void
    {
        $this->travelTo('2027-03-01 09:00:00');
        $n = $this->ncr();
        $this->assertSame('NCR/2027/001', $n->number);
        $this->assertSame('NCR/2027/002', $this->ncr()->number);

        $n = $this->toMonitoring();
        $this->assertSame(Ncr::MONITORING, $n->status);
        $this->assertSame([30, 60, 90], $n->checks()->pluck('day')->all());
        $this->assertSame('2027-03-31', $n->checks()->where('day', 30)->value('due_date')->toDateString());

        foreach ([30, 60, 90] as $day) {
            $this->travelTo(now()->setDate(2027, 3, 1)->addDays($day));
            $check = $n->checks()->where('day', $day)->first();
            $this->wf->recordEffectiveness($this->mr, $check, 'efektif', "Tidak berulang hari ke-{$day}");
        }
        $n->refresh();
        $this->assertSame(Ncr::CLOSING, $n->status);
        Notification::assertSentTo($this->gm, SmmNotification::class, fn ($x) => str_contains($x->subject, 'Mohon penutupan'));

        $this->wf->close($this->gm, $n, 'Efektif');
        $n->refresh();
        $this->assertSame(Ncr::CLOSED, $n->status);
        $this->assertSame($this->gm->name, $n->closed_by_name);
        $this->assertNotNull($n->closed_at);
    }

    public function test_mulai_tindakan_butuh_koreksi_akar_masalah_dan_tindakan(): void
    {
        $n = $this->ncr();
        try {
            $this->wf->startActions($this->kadiv, $n);
            $this->fail('Harus ditolak');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('akar masalah', $e->errors()['status'][0]);
        }
    }

    public function test_pemantauan_tidak_bisa_diisi_sebelum_waktunya_dan_harus_berurutan(): void
    {
        $n = $this->toMonitoring();
        $c30 = $n->checks()->where('day', 30)->first();
        $c60 = $n->checks()->where('day', 60)->first();
        try {
            $this->wf->recordEffectiveness($this->mr, $c30, 'efektif', 'terlalu cepat');
            $this->fail('Belum waktunya');
        } catch (ValidationException) {
        }
        $this->travel(61)->days();
        $this->expectException(ValidationException::class);
        $this->wf->recordEffectiveness($this->mr, $c60, 'efektif', 'lompat urutan');
    }

    public function test_tidak_efektif_membuka_ulang_dengan_siklus_baru(): void
    {
        $n = $this->toMonitoring();
        $this->travel(30)->days();
        $this->wf->recordEffectiveness($this->mr, $n->checks()->where('day', 30)->first(), 'tidak_efektif', 'Masalah berulang di kelas X');
        $n->refresh();
        $this->assertSame(Ncr::OPEN, $n->status);
        $this->assertSame(1, $n->reopened_count);
        $this->assertSame(2, $n->currentCycle());
        $this->assertNull($n->root_cause, 'Akar masalah wajib dianalisis ulang');
        $this->assertSame(1, $n->checks()->count(), 'Jadwal hari 60 & 90 siklus lama dibatalkan, hasil hari 30 tetap tersimpan');
        Notification::assertSentTo($this->kadiv, SmmNotification::class, fn ($x) => str_contains($x->subject, 'dibuka ulang'));

        // Siklus 2 butuh tindakan baru (tindakan siklus 1 tidak dihitung)
        $this->wf->recordAnalysis($this->kadiv, $n, null, 'fishbone', 'Akar masalah baru');
        try {
            $this->wf->startActions($this->kadiv, $n->fresh());
            $this->fail('Butuh tindakan siklus baru');
        } catch (ValidationException) {
        }
        $this->wf->addAction($this->kadiv, $n->fresh(), 'Tindakan B', $this->staf->id, now()->addDays(7)->toDateString());
        $this->wf->startActions($this->kadiv, $n->fresh());
        $this->assertSame(Ncr::ACTION, $n->fresh()->status);
    }

    public function test_hak_akses_alur(): void
    {
        $n = $this->ncr();
        // Staf tidak bisa membuat NCR
        try {
            $this->ncr($this->staf);
            $this->fail('Staf tidak boleh membuat NCR');
        } catch (AuthorizationException) {
        }
        $this->wf->recordAnalysis($this->kadiv, $n, 'K', '5why', 'A');
        $a = $this->wf->addAction($this->kadiv, $n->fresh(), 'Tindakan', $this->staf->id, now()->addDays(3)->toDateString());
        $this->wf->startActions($this->kadiv, $n->fresh());

        // Hanya PIC tindakan / pengelola yang bisa menyelesaikan tindakan
        $other = $this->makeUser('staf', 'CS');
        try {
            $this->wf->completeAction($other, $a->fresh(), 'x');
            $this->fail('Staf lain tidak boleh');
        } catch (AuthorizationException) {
        }
        $this->wf->completeAction($this->staf, $a->fresh(), 'Selesai');

        // PIC NCR (kadiv) tidak boleh memverifikasi pekerjaannya sendiri; kadiv juga tidak punya izin verifikasi
        try {
            $this->wf->verify($this->kadiv, $n->fresh(), 'ok');
            $this->fail('Pemisahan tugas');
        } catch (AuthorizationException) {
        }
        $this->wf->verify($this->mr, $n->fresh(), 'ok');

        // Penutupan sesuai matriks: GM (bawaan). MR punya izin tutup tapi bukan peran pengesah.
        $n = $n->fresh();
        foreach ([30, 60, 90] as $d) {
            $this->travelTo(now()->startOfDay()->addDays($d === 30 ? 30 : 30));
            $this->wf->recordEffectiveness($this->mr, $n->checks()->where('day', $d)->first(), 'efektif', 'ok');
        }
        try {
            $this->wf->close($this->mr, $n->fresh(), 'x');
            $this->fail('MR bukan pengesah capa_closure');
        } catch (AuthorizationException) {
        }
        $this->wf->close($this->gm, $n->fresh(), null);
        $this->assertSame(Ncr::CLOSED, $n->fresh()->status);
    }

    public function test_penolakan_penutupan_membuka_ulang(): void
    {
        $n = $this->toMonitoring();
        foreach ([30, 60, 90] as $d) {
            $this->travel(30)->days();
            $this->wf->recordEffectiveness($this->mr, $n->checks()->where('day', $d)->first(), 'efektif', 'ok');
        }
        $this->wf->rejectClosure($this->gm, $n->fresh(), 'Bukti kurang kuat');
        $this->assertSame(Ncr::OPEN, $n->fresh()->status);
        $this->assertSame(1, $n->fresh()->reopened_count);
    }

    public function test_staf_hanya_melihat_ncr_yang_terkait_dengannya(): void
    {
        $n = $this->ncr();
        $this->actingAs($this->staf)->get(route('ncrs.show', $n))->assertForbidden();
        $this->actingAs($this->staf)->get(route('ncrs.index'))->assertOk()->assertDontSee($n->number);
        $this->wf->addAction($this->kadiv, $n, 'Tugas untuk staf', $this->staf->id, now()->addDays(5)->toDateString());
        $this->actingAs($this->staf)->get(route('ncrs.show', $n))->assertOk();
        $this->actingAs($this->staf)->get(route('ncrs.index'))->assertSee($n->number);
    }

    public function test_pengingat_tindakan_dan_pemantauan(): void
    {
        $n = $this->ncr();
        $this->wf->recordAnalysis($this->kadiv, $n, 'K', '5why', 'A');
        $a = $this->wf->addAction($this->kadiv, $n->fresh(), 'Tindakan', $this->staf->id, now()->addDays(10)->toDateString());
        $this->wf->startActions($this->kadiv, $n->fresh());
        $r = app(Reminders::class);
        $this->assertSame(0, $r->capaActions());
        $this->travel(8)->days();
        $this->assertSame(1, $r->capaActions(), 'H-2: segera jatuh tempo');
        $this->assertSame(0, $r->capaActions(), 'tidak dobel');
        $this->travel(3)->days();
        $this->assertSame(1, $r->capaActions(), 'lewat tenggat');

        $this->wf->completeAction($this->staf, $a->fresh(), 'ok');
        $this->wf->verify($this->mr, $n->fresh(), 'ok');
        $this->assertSame(0, $r->effectiveness());
        $this->travel(30)->days();
        $this->assertSame(1, $r->effectiveness());
        $this->assertSame(0, $r->effectiveness());
    }

    public function test_cetak_ncr(): void
    {
        $n = $this->toMonitoring();
        $this->actingAs($this->gm)->get(route('ncrs.print', $n))->assertOk()->assertSee('LAPORAN KETIDAKSESUAIAN')->assertSee($n->number)->assertSee('Tindakan A');
    }
}
