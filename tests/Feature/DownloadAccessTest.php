<?php

namespace Tests\Feature;

use App\Domain\Documents\DocumentWorkflow;
use App\Models\Division;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Hak akses unduhan diperiksa di server untuk setiap permintaan (bukan sekadar menyembunyikan tombol).
 */
class DownloadAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $gm, $direktur, $mr, $kadivOps, $stafOps, $stafCs, $trainer, $auditor;
    private Document $doc;
    private DocumentRevision $old, $cur;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seedBase();
        $this->gm = $this->makeUser('gm');
        $this->direktur = $this->makeUser('direktur');
        $this->mr = $this->makeUser('mr');
        $this->kadivOps = $this->makeUser('kadiv', 'OPS');
        $this->stafOps = $this->makeUser('staf', 'OPS');
        $this->stafCs = $this->makeUser('staf', 'CS');
        $this->trainer = $this->makeUser('trainer', null);
        $this->auditor = $this->makeUser('auditor', null, ['access_expires_at' => now()->addMonth()]);

        $wf = app(DocumentWorkflow::class);
        // SOP divisi OPS, cakupan terbatas + peran trainer, dengan file sumber DOCX.
        $this->doc = $wf->create($this->gm, [
            'document_type_id' => DocumentType::where('code', 'SOP')->value('id'),
            'division_id' => Division::where('code', 'OPS')->value('id'),
            'title' => 'Penyelenggaraan Pelatihan', 'access_scope' => 'terbatas', 'access_roles' => ['trainer'],
        ], $this->pdf('Rev 00'), $this->docx());
        $this->old = $this->doc->revisions()->first();
        $this->approve($wf, $this->old);
        $this->cur = $wf->createRevision($this->gm, $this->doc->fresh(), 'Perbaikan', 'Rev 01', $this->pdf('Rev 01'));
        $this->approve($wf, $this->cur);
        $this->old->refresh();
        $this->cur->refresh();
    }

    private function approve(DocumentWorkflow $wf, DocumentRevision $r): void
    {
        $wf->submit($this->gm, $r, $this->mr->id);
        $wf->reviewPass($this->mr, $r->fresh());
        $wf->approve($this->direktur, $r->fresh());
    }

    private function docx(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'smmd').'.docx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types/>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><document/>');
        $zip->close();

        return new UploadedFile($path, 'sumber.docx', null, null, true);
    }

    private function url(DocumentRevision $r, string $kind = 'pdf'): string
    {
        return route('documents.download', [$this->doc, $r, $kind]);
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get($this->url($this->cur))->assertRedirect(route('login'));
    }

    public function test_revisi_berlaku_sesuai_cakupan_akses(): void
    {
        foreach (['gm', 'direktur', 'mr', 'kadivOps', 'stafOps', 'trainer', 'auditor'] as $who) {
            $this->actingAs($this->{$who})->get($this->url($this->cur))->assertOk()->assertHeader('content-type', 'application/pdf');
        }
        // Staf CS: bukan divisi OPS, bukan peran yang diberi akses.
        $this->actingAs($this->stafCs)->get($this->url($this->cur))->assertForbidden();
        $this->actingAs($this->stafCs)->get(route('documents.show', $this->doc))->assertForbidden();
    }

    public function test_dokumen_umum_bisa_diunduh_semua_pengguna(): void
    {
        $this->doc->update(['access_scope' => 'umum']);
        $this->actingAs($this->stafCs)->get($this->url($this->cur))->assertOk();
    }

    public function test_revisi_kedaluwarsa_hanya_gm_mr_direktur_auditor_dengan_tanda_air(): void
    {
        foreach (['gm', 'direktur', 'mr', 'auditor'] as $who) {
            $res = $this->actingAs($this->{$who})->get($this->url($this->old))->assertOk();
            $file = $res->baseResponse->getFile()->getPathname();
            $this->assertPdfContains($file, 'TIDAK TERKENDALI');
        }
        foreach (['kadivOps', 'stafOps', 'trainer', 'stafCs'] as $who) {
            $this->actingAs($this->{$who})->get($this->url($this->old))->assertForbidden();
        }
    }

    public function test_salinan_berlaku_diberi_cap_salinan_terkendali(): void
    {
        $res = $this->actingAs($this->stafOps)->get($this->url($this->cur))->assertOk();
        $this->assertPdfContains($res->baseResponse->getFile()->getPathname(), 'SALINAN TERKENDALI');
    }

    public function test_file_sumber_hanya_gm_mr_direktur(): void
    {
        $url = $this->url($this->old, 'sumber');
        $this->actingAs($this->auditor)->get($url)->assertForbidden(); // auditor: tidak punya izin file sumber
        $this->actingAs($this->kadivOps)->get($url)->assertForbidden();
        $this->actingAs($this->gm)->get($url)->assertOk();
        $this->actingAs($this->mr)->get($url)->assertOk();
    }

    public function test_draf_hanya_untuk_pengelola_dan_pereviu(): void
    {
        $draft = app(DocumentWorkflow::class)->createRevision($this->gm, $this->doc->fresh(), 'Alasan', 'Rev 02', $this->pdf('Rev 02'));
        $this->actingAs($this->gm)->get($this->url($draft))->assertOk();
        $this->actingAs($this->kadivOps)->get($this->url($draft))->assertOk(); // kadiv mengelola dokumen divisinya sendiri
        $kadivCs = $this->makeUser('kadiv', 'CS');
        foreach (['stafOps', 'trainer', 'auditor'] as $who) {
            $this->assertSame(403, $this->actingAs($this->{$who})->get($this->url($draft))->getStatusCode(), $who);
        }
        $this->actingAs($kadivCs)->get($this->url($draft))->assertForbidden();
        app(DocumentWorkflow::class)->submit($this->gm, $draft, $kadivCs->id);
        $this->actingAs($kadivCs)->get($this->url($draft->fresh()))->assertOk(); // pereviu yang ditunjuk
    }

    public function test_revisi_dokumen_lain_tidak_bisa_diakses_lewat_url_campuran(): void
    {
        $other = app(DocumentWorkflow::class)->create($this->gm, [
            'document_type_id' => DocumentType::where('code', 'IK')->value('id'),
            'division_id' => Division::where('code', 'CS')->value('id'), 'title' => 'Lain', 'access_scope' => 'umum',
        ], $this->pdf());
        $this->actingAs($this->gm)->get(route('documents.download', [$other, $this->cur, 'pdf']))->assertNotFound();
    }

    public function test_akun_nonaktif_dan_auditor_kedaluwarsa_ditolak(): void
    {
        $this->stafOps->update(['is_active' => false]);
        $this->actingAs($this->stafOps)->get($this->url($this->cur))->assertRedirect(route('login'));

        $this->auditor->update(['access_expires_at' => now()->subDay()]);
        $this->actingAs($this->auditor)->get($this->url($this->cur))->assertRedirect(route('login'));
        auth()->logout();
        $this->post(route('login'), ['email' => $this->auditor->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_unduhan_tercatat_di_log(): void
    {
        $this->actingAs($this->stafOps)->get($this->url($this->cur))->assertOk();
        $this->assertDatabaseHas('activity_logs', ['action' => 'unduh', 'user_id' => $this->stafOps->id]);
    }

    public function test_tidak_ada_jalur_langsung_ke_berkas(): void
    {
        $this->assertDirectoryDoesNotExist(public_path('storage'));
        $file = $this->cur->pdfFile;
        $this->get('/'.$file->drive_folder_path.'/'.$file->drive_name)->assertNotFound();
    }

    private function assertPdfContains(string $path, string $needle): void
    {
        $raw = file_get_contents($path);
        $text = $raw;
        // Buka stream terkompresi (FlateDecode) untuk mencari teks tanda air.
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $m)) {
            foreach ($m[1] as $s) {
                $d = @gzuncompress($s);
                $text .= $d === false ? '' : $d;
            }
        }
        $this->assertStringContainsString($needle, $text, "PDF tidak memuat teks '{$needle}'");
    }
}
