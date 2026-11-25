<?php

namespace Database\Seeders;

use App\Domain\Documents\DocumentWorkflow;
use App\Domain\ExternalDocuments\ExternalDocumentService;
use App\Models\Division;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\ExternalDocumentCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Data contoh untuk komputer lokal / uji coba. JANGAN dijalankan di produksi.
 * Kata sandi semua akun contoh: password
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        config(['queue.default' => 'sync']);
        $div = fn (string $code) => Division::where('code', $code)->value('id');

        $users = [
            ['gm', 'Rina Wulandari', 'gm@contoh.test', 'General Manager', 'QA'],
            ['direktur', 'Budi Santoso', 'direktur@contoh.test', 'Direktur', 'QA'],
            ['mr', 'Sari Pratiwi', 'mr@contoh.test', 'Wakil Manajemen Mutu', 'QA'],
            ['kadiv', 'Agus Setiawan', 'kadiv.ops@contoh.test', 'Kepala Divisi Operasional', 'OPS'],
            ['staf', 'Dewi Lestari', 'cs@contoh.test', 'Staf Customer Service', 'CS'],
            ['trainer', 'Hendra Gunawan', 'trainer@contoh.test', 'Trainer K3', 'OPS'],
            ['auditor', 'Maya Kusuma', 'auditor@contoh.test', 'Auditor Internal', null],
        ];
        $u = [];
        foreach ($users as [$role, $name, $email, $position, $division]) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name, 'password' => 'password', 'position' => $position,
                'division_id' => $division ? $div($division) : null,
                'access_expires_at' => $role === 'auditor' ? now()->addMonths(3) : null,
            ]);
            $user->syncRoles([$role]);
            $u[$role] = $user;
        }
        Division::where('code', 'OPS')->update(['head_user_id' => $u['kadiv']->id]);

        if (Document::exists()) {
            return;
        }

        $wf = app(DocumentWorkflow::class);
        $type = fn (string $c) => DocumentType::where('code', $c)->value('id');

        // 1) SOP Penyelenggaraan Pelatihan: Rev 00 disahkan, lalu Rev 01 disahkan → Rev 00 kedaluwarsa.
        $sop = $wf->create($u['gm'], [
            'document_type_id' => $type('SOP'), 'division_id' => $div('OPS'),
            'title' => 'Penyelenggaraan Pelatihan K3', 'summary' => 'Alur perencanaan, pelaksanaan, dan evaluasi kelas pelatihan K3.',
            'access_scope' => Document::SCOPE_RESTRICTED, 'access_roles' => ['trainer'], 'access_divisions' => [$div('CS')],
        ], $this->pdf('ASI-SOP-OPS-01 Rev. 00', 'Penyelenggaraan Pelatihan K3'));
        $rev = $sop->revisions()->first();
        $wf->submit($u['gm'], $rev, $u['kadiv']->id);
        $wf->reviewPass($u['kadiv'], $rev->fresh(), 'Sesuai praktik lapangan.');
        $wf->approve($u['direktur'], $rev->fresh());

        $rev1 = $wf->createRevision($u['gm'], $sop->fresh(), 'Penambahan langkah evaluasi trainer oleh peserta', 'Tambah bagian 6.4 Evaluasi trainer', $this->pdf('ASI-SOP-OPS-01 Rev. 01', 'Penyelenggaraan Pelatihan K3'));
        $wf->submit($u['gm'], $rev1, $u['mr']->id);
        $wf->reviewPass($u['mr'], $rev1->fresh());
        $wf->approve($u['direktur'], $rev1->fresh(), 'Disetujui.');

        // 2) IK Registrasi Peserta: disahkan GM (dibuat MR, direviu Kadiv).
        $ik = $wf->create($u['mr'], [
            'document_type_id' => $type('IK'), 'division_id' => $div('CS'),
            'title' => 'Registrasi Peserta Pelatihan', 'summary' => 'Langkah registrasi peserta dari leads hingga konfirmasi kelas.',
            'access_scope' => Document::SCOPE_PUBLIC,
        ], $this->pdf('ASI-IK-CS-01 Rev. 00', 'Registrasi Peserta Pelatihan'));
        $r = $ik->revisions()->first();
        $wf->submit($u['mr'], $r, $u['kadiv']->id);
        $wf->reviewPass($u['kadiv'], $r->fresh());
        $wf->approve($u['gm'], $r->fresh());

        // 3) Formulir Daftar Hadir: masih menunggu review MR.
        $f = $wf->create($u['gm'], [
            'document_type_id' => $type('F'), 'division_id' => $div('OPS'),
            'title' => 'Daftar Hadir Peserta', 'summary' => 'Formulir daftar hadir harian kelas pelatihan.',
            'access_scope' => Document::SCOPE_RESTRICTED, 'access_roles' => ['trainer'],
        ], $this->pdf('ASI-F-OPS-01 Rev. 00', 'Daftar Hadir Peserta'));
        $wf->submit($u['gm'], $f->revisions()->first(), $u['mr']->id);

        // Legalitas
        $ext = app(ExternalDocumentService::class);
        $cat = fn (string $c) => ExternalDocumentCategory::where('code', $c)->value('id');
        $ext->create($u['gm'], [
            'external_document_category_id' => $cat('SKP'), 'title' => 'SKP PJK3 Bidang Pembinaan', 'issuer' => 'Kementerian Ketenagakerjaan RI',
            'original_number' => '5/1234/AS.01.02/XI/2024', 'issued_at' => now()->subYears(2)->subMonths(10)->toDateString(),
            'expires_at' => now()->addDays(60)->toDateString(), 'verified' => true,
        ], $this->pdf('SKP PJK3', 'Surat Keterangan Penunjukan PJK3 (contoh)'));
        $ext->create($u['gm'], [
            'external_document_category_id' => $cat('AKT'), 'title' => 'Akta Pendirian PT Artha Safety Indonesia', 'issuer' => 'Notaris (contoh)',
            'original_number' => '12', 'issued_at' => '2019-03-14', 'expires_at' => null, 'verified' => true,
        ], $this->pdf('Akta Pendirian', 'Akta Pendirian (contoh)'));
    }

    private function pdf(string $code, string $title): UploadedFile
    {
        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'PT Artha Safety Indonesia', 0, 1);
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 8, $code, 0, 1);
        $pdf->Cell(0, 8, $title, 0, 1);
        $pdf->Ln(6);
        $pdf->MultiCell(0, 6, 'Dokumen contoh untuk uji coba Sistem Manajemen Mutu. Isi dokumen sebenarnya disusun oleh GM.');
        $path = tempnam(sys_get_temp_dir(), 'smm').'.pdf';
        $pdf->Output('F', $path);

        return new UploadedFile($path, $code.'.pdf', 'application/pdf', null, true);
    }
}
