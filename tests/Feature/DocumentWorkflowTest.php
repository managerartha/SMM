<?php

namespace Tests\Feature;

use App\Domain\Documents\DocumentWorkflow;
use App\Models\Division;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\DocumentType;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private DocumentWorkflow $wf;
    private User $gm;
    private User $direktur;
    private User $mr;
    private User $kadiv;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seedBase();
        $this->wf = app(DocumentWorkflow::class);
        $this->gm = $this->makeUser('gm');
        $this->direktur = $this->makeUser('direktur');
        $this->mr = $this->makeUser('mr');
        $this->kadiv = $this->makeUser('kadiv', 'OPS');
    }

    private function newDoc(string $type = 'SOP', ?User $by = null, string $div = 'OPS'): Document
    {
        return $this->wf->create($by ?? $this->gm, [
            'document_type_id' => DocumentType::where('code', $type)->value('id'),
            'division_id' => Division::where('code', $div)->value('id'),
            'title' => 'Dokumen '.$type, 'access_scope' => 'terbatas',
        ], $this->pdf());
    }

    public function test_alur_lengkap_draf_review_berlaku_revisi_kedaluwarsa(): void
    {
        $doc = $this->newDoc();
        $this->assertSame('ASI-SOP-OPS-01', $doc->number);
        $r0 = $doc->revisions()->first();
        $this->assertSame(DocumentRevision::DRAFT, $r0->status);
        $this->assertSame(StoredFile::STORED, $r0->pdfFile->state, 'Berkas sudah dipindah ke Drive (lokal saat tes)');
        $this->assertNull($r0->pdfFile->local_path, 'Salinan di server dihapus setelah di Drive');

        $this->wf->submit($this->gm, $r0, $this->kadiv->id);
        $this->assertSame(DocumentRevision::REVIEW, $r0->fresh()->status);
        $this->wf->reviewPass($this->kadiv, $r0->fresh());
        $this->assertSame(DocumentRevision::APPROVAL, $r0->fresh()->status);
        $this->wf->approve($this->direktur, $r0->fresh());

        $r0->refresh();
        $doc->refresh();
        $this->assertSame(DocumentRevision::EFFECTIVE, $r0->status);
        $this->assertSame($this->direktur->name, $r0->approved_by_name);
        $this->assertSame($this->direktur->position, $r0->approved_by_position);
        $this->assertNotNull($r0->approved_at);
        $this->assertSame($r0->id, $doc->current_revision_id);
        $this->assertTrue($doc->next_review_date->isSameDay(now()->addYear()));

        // Revisi: nomor dokumen tetap, nomor revisi naik, revisi lama kedaluwarsa saat yang baru disahkan.
        $r1 = $this->wf->createRevision($this->gm, $doc, 'Perubahan regulasi', 'Ubah bab 4', $this->pdf('rev1'));
        $this->assertSame(1, $r1->revision_no);
        $this->assertSame('ASI-SOP-OPS-01 Rev. 01', $r1->fullLabel());
        $this->assertSame(DocumentRevision::EFFECTIVE, $r0->fresh()->status, 'Rev lama tetap berlaku selama revisi baru diproses');

        $this->wf->submit($this->gm, $r1, $this->mr->id);
        $this->wf->reviewPass($this->mr, $r1->fresh());
        $this->wf->approve($this->direktur, $r1->fresh());

        $this->assertSame(DocumentRevision::EXPIRED, $r0->fresh()->status);
        $this->assertSame($r1->id, $r0->fresh()->superseded_by_id);
        $this->assertSame(DocumentRevision::EFFECTIVE, $r1->fresh()->status);
        $this->assertSame('ASI-SOP-OPS-01', $doc->fresh()->number);
        $this->assertSame(1, DocumentRevision::where('document_id', $doc->id)->where('status', 'berlaku')->count());
        $this->assertSame(['dibuat', 'diajukan', 'diteruskan', 'disahkan', 'kedaluwarsa'], $r0->events()->pluck('action')->all());
    }

    public function test_database_menolak_dua_revisi_berlaku(): void
    {
        $doc = $this->newDoc();
        DocumentRevision::where('document_id', $doc->id)->update(['status' => 'berlaku']);
        $this->expectException(QueryException::class);
        $doc->revisions()->create(['revision_no' => 5, 'status' => 'berlaku', 'created_by' => $this->gm->id]);
    }

    public function test_penolakan_wajib_catatan_dan_kembali_ke_draf(): void
    {
        $r = $this->newDoc()->revisions()->first();
        $this->wf->submit($this->gm, $r, $this->kadiv->id);
        try {
            $this->wf->reviewReject($this->kadiv, $r->fresh(), '   ');
            $this->fail('Penolakan tanpa catatan harus ditolak');
        } catch (ValidationException) {
        }
        $this->wf->reviewReject($this->kadiv, $r->fresh(), 'Langkah 3 kurang jelas');
        $this->assertSame(DocumentRevision::DRAFT, $r->fresh()->status);
        $this->assertSame('Langkah 3 kurang jelas', $r->events()->where('action', 'ditolak_review')->value('note'));

        // Ditolak di pengesahan juga kembali ke draf.
        $this->wf->submit($this->gm, $r->fresh(), $this->kadiv->id);
        $this->wf->reviewPass($this->kadiv, $r->fresh());
        $this->wf->rejectApproval($this->direktur, $r->fresh(), 'Sesuaikan dengan regulasi terbaru');
        $this->assertSame(DocumentRevision::DRAFT, $r->fresh()->status);
    }

    public function test_hanya_pereviu_yang_ditunjuk_yang_bisa_mereviu(): void
    {
        $r = $this->newDoc()->revisions()->first();
        $this->wf->submit($this->gm, $r, $this->kadiv->id);
        $this->expectException(AuthorizationException::class);
        $this->wf->reviewPass($this->mr, $r->fresh());
    }

    public function test_pengesahan_mengikuti_matriks(): void
    {
        $r = $this->newDoc('SOP')->revisions()->first();
        $this->wf->submit($this->gm, $r, $this->kadiv->id);
        $this->wf->reviewPass($this->kadiv, $r->fresh());
        try {
            $this->wf->approve($this->gm, $r->fresh()); // SOP disahkan Direktur, bukan GM
            $this->fail('GM tidak boleh mengesahkan SOP');
        } catch (AuthorizationException) {
        }
        $this->wf->approve($this->direktur, $r->fresh());
        $this->assertSame('berlaku', $r->fresh()->status);
    }

    public function test_pembuat_tidak_bisa_mengesahkan_sendiri_tanpa_review_orang_lain(): void
    {
        // Formulir: review tidak wajib dan disahkan GM. Karena pembuatnya GM, review tetap dipaksa.
        $r = $this->newDoc('F')->revisions()->first();
        try {
            $this->wf->submit($this->gm, $r, null);
            $this->fail('Pengajuan tanpa pereviu harus ditolak bila pembuat = pengesah');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reviewer_id', $e->errors());
        }
        $this->wf->submit($this->gm, $r->fresh(), $this->mr->id);
        $this->wf->reviewPass($this->mr, $r->fresh());
        $this->wf->approve($this->gm, $r->fresh());
        $this->assertSame('berlaku', $r->fresh()->status);
    }

    public function test_formulir_buatan_orang_lain_langsung_ke_pengesahan(): void
    {
        $r = $this->newDoc('F', $this->mr)->revisions()->first();
        $this->wf->submit($this->mr, $r, null);
        $this->assertSame(DocumentRevision::APPROVAL, $r->fresh()->status);
        $this->wf->approve($this->gm, $r->fresh());
        $this->assertSame('berlaku', $r->fresh()->status);
    }

    public function test_pereviu_tidak_boleh_pembuat_sendiri(): void
    {
        $r = $this->newDoc()->revisions()->first();
        $this->expectException(ValidationException::class);
        $this->wf->submit($this->gm, $r, $this->gm->id);
    }

    public function test_revisi_hanya_dari_versi_berlaku_dan_satu_per_satu(): void
    {
        $doc = $this->newDoc();
        try {
            $this->wf->createRevision($this->gm, $doc, 'x', 'y', $this->pdf());
            $this->fail('Belum ada revisi berlaku');
        } catch (ValidationException) {
        }
        $r = $doc->revisions()->first();
        $this->wf->submit($this->gm, $r, $this->kadiv->id);
        $this->wf->reviewPass($this->kadiv, $r->fresh());
        $this->wf->approve($this->direktur, $r->fresh());

        try {
            $this->wf->createRevision($this->gm, $doc->fresh(), '', 'y', $this->pdf());
            $this->fail('Alasan perubahan wajib');
        } catch (ValidationException) {
        }
        $this->wf->createRevision($this->gm, $doc->fresh(), 'Alasan', 'y', $this->pdf());
        $this->expectException(ValidationException::class);
        $this->wf->createRevision($this->gm, $doc->fresh(), 'Alasan lain', 'z', $this->pdf());
    }

    public function test_kadiv_hanya_mengelola_dokumen_divisinya(): void
    {
        $this->kadiv->givePermissionTo('dokumen.kelola-divisi');
        $doc = $this->newDoc('IK', $this->kadiv, 'OPS');
        $this->assertSame('ASI-IK-OPS-01', $doc->number);
        $this->expectException(ValidationException::class);
        $this->newDoc('IK', $this->kadiv, 'CS');
    }

    public function test_draf_yang_dibatalkan_tidak_memakai_ulang_nomor(): void
    {
        $doc = $this->newDoc();
        $this->wf->cancel($this->gm, $doc->revisions()->first(), 'Salah jenis');
        $this->assertTrue($doc->fresh()->isArchived());
        $this->assertSame('ASI-SOP-OPS-02', $this->newDoc()->number);
    }

    public function test_notifikasi_dikirim_ke_pereviu_dan_pengesah(): void
    {
        $r = $this->newDoc()->revisions()->first();
        $this->wf->submit($this->gm, $r, $this->kadiv->id);
        Notification::assertSentTo($this->kadiv, \App\Notifications\SmmNotification::class);
        $this->wf->reviewPass($this->kadiv, $r->fresh());
        Notification::assertSentTo($this->direktur, \App\Notifications\SmmNotification::class);
        Notification::assertNotSentTo($this->mr, \App\Notifications\SmmNotification::class);
    }
}
