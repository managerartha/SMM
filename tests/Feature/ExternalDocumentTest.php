<?php

namespace Tests\Feature;

use App\Domain\ExternalDocuments\ExternalDocumentService;
use App\Domain\ExternalDocuments\ValidityStatus;
use App\Domain\Notifications\Reminders;
use App\Models\ExternalDocument;
use App\Models\ExternalDocumentCategory;
use App\Models\User;
use App\Notifications\SmmNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ExternalDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $gm;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seedBase();
        $this->gm = $this->makeUser('gm');
    }

    private function make(?string $expires, string $cat = 'SKP'): ExternalDocument
    {
        return app(ExternalDocumentService::class)->create($this->gm, [
            'external_document_category_id' => ExternalDocumentCategory::where('code', $cat)->value('id'),
            'title' => 'SKP PJK3', 'issuer' => 'Kemnaker', 'original_number' => 'KEP.123/2025',
            'issued_at' => now()->subYear()->toDateString(), 'expires_at' => $expires, 'verified' => true,
        ], $this->pdf());
    }

    public function test_nomor_internal_dan_verifikasi(): void
    {
        $d = $this->make(now()->addYear()->toDateString());
        $this->assertSame('DE-SKP-001', $d->number);
        $this->assertSame('DE-AKT-001', $this->make(null, 'AKT')->number);
        $v = $d->currentVersion;
        $this->assertSame('KEP.123/2025', $v->original_number);
        $this->assertSame($this->gm->id, $v->verified_by);
        $this->assertNotNull($v->verified_at);
    }

    public function test_status_masa_berlaku_dihitung_otomatis(): void
    {
        $this->assertSame(ValidityStatus::Valid, $this->make(now()->addDays(91)->toDateString())->currentVersion->validity());
        $this->assertSame(ValidityStatus::Soon, $this->make(now()->addDays(90)->toDateString())->currentVersion->validity());
        $this->assertSame(ValidityStatus::Soon, $this->make(now()->toDateString())->currentVersion->validity());
        $this->assertSame(ValidityStatus::Expired, $this->make(now()->subDay()->toDateString())->currentVersion->validity());
        $this->assertSame(ValidityStatus::NoExpiry, $this->make(null)->currentVersion->validity());
    }

    public function test_perpanjangan_menjadi_versi_baru_dan_versi_lama_tetap_ada(): void
    {
        $d = $this->make(now()->addDays(10)->toDateString());
        $old = $d->currentVersion;
        app(ExternalDocumentService::class)->renew($this->gm, $d, [
            'original_number' => 'KEP.999/2026', 'issued_at' => now()->toDateString(), 'expires_at' => now()->addYears(3)->toDateString(),
        ], $this->pdf('baru'));
        $d->refresh();
        $this->assertSame('DE-SKP-001', $d->number);
        $this->assertSame(2, $d->currentVersion->version_no);
        $this->assertCount(2, $d->versions);
        $this->assertNotNull($old->fresh());
        $this->assertSame(ValidityStatus::Valid, $d->currentVersion->validity());
    }

    public function test_pengingat_90_60_30_hari_tidak_terkirim_ganda(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(7));
        $d = $this->make(now()->addDays(100)->toDateString());
        $r = app(Reminders::class);

        $this->assertSame(0, $r->external(), 'Belum 90 hari');
        $this->travel(10)->days();
        $this->assertSame(1, $r->external(), 'H-90');
        $this->assertSame(0, $r->external(), 'Tidak dikirim ulang di hari yang sama');
        $this->travel(1)->days();
        $this->assertSame(0, $r->external(), 'H-89: tidak ada pengingat baru');
        $this->travel(29)->days();
        $this->assertSame(1, $r->external(), 'H-60');
        $this->travel(30)->days();
        $this->assertSame(1, $r->external(), 'H-30');
        $this->travel(31)->days();
        $this->assertSame(1, $r->external(), 'Sudah kedaluwarsa');
        $this->assertSame(0, $r->external());
        Notification::assertSentToTimes($this->gm, SmmNotification::class, 4);
    }

    public function test_staf_tidak_bisa_melihat_legalitas(): void
    {
        $d = $this->make(null);
        $staf = $this->makeUser('staf', 'CS');
        $this->actingAs($staf)->get(route('external-documents.index'))->assertForbidden();
        $this->actingAs($staf)->get(route('external-documents.download', [$d, $d->currentVersion]))->assertForbidden();
        $this->actingAs($this->makeUser('auditor', null, ['access_expires_at' => now()->addWeek()]))
            ->get(route('external-documents.download', [$d, $d->currentVersion]))->assertOk();
    }
}
