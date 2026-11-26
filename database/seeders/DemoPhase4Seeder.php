<?php

namespace Database\Seeders;

use App\Domain\Audits\AuditService;
use App\Domain\Innovations\InnovationService;
use App\Domain\Reports\PerformanceReportService;
use App\Domain\Reviews\ManagementReviewService;
use App\Models\AuditProgram;
use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Data contoh Tahap 4 (lokal saja): program & audit, tinjauan manajemen, inovasi, laporan kinerja. */
class DemoPhase4Seeder extends Seeder
{
    public function run(): void
    {
        if (AuditProgram::exists()) {
            return;
        }
        config(['queue.default' => 'sync']);
        $u = fn ($e) => User::where('email', $e)->firstOrFail();
        [$gm, $dir, $mr, $kadiv, $cs, $auditor] = [$u('gm@contoh.test'), $u('direktur@contoh.test'), $u('mr@contoh.test'), $u('kadiv.ops@contoh.test'), $u('cs@contoh.test'), $u('auditor@contoh.test')];
        $div = fn ($c) => Division::where('code', $c)->value('id');
        $audits = app(AuditService::class);
        $year = (int) now()->format('Y');

        $p = $audits->saveProgram($mr, ['year' => $year, 'objective' => 'Memastikan SMM sesuai ISO 9001:2015 & persyaratan PJK3 serta diterapkan efektif.',
            'scope' => 'Operasional pelatihan, customer service, mutu.', 'criteria' => 'ISO 9001:2015, Permenaker PJK3, SOP internal']);
        $a1 = $audits->schedule($mr, $p, ['division_id' => $div('OPS'), 'process' => 'Penyelenggaraan pelatihan', 'planned_date' => now()->startOfYear()->addDays(40)->toDateString(), 'auditor_id' => $auditor->id]);
        $audits->schedule($mr, $p, ['division_id' => $div('CS'), 'process' => 'Penanganan pelanggan', 'planned_date' => now()->addDays(5)->min(now()->endOfYear())->toDateString(), 'auditor_id' => $auditor->id]);
        $audits->submitProgram($mr, $p);
        $audits->decideProgram($dir, $p->fresh(), true, null);

        // Audit 1 selesai dilaksanakan: checklist, temuan minor → NCR otomatis, laporan diajukan
        $items = $a1->items()->get()->mapWithKeys(fn ($it, $n) => [$it->id => ['result' => $n === 3 ? 'tidak_sesuai' : 'sesuai', 'evidence' => $n === 3 ? 'Daftar hadir kelas P3K-B02 hari ke-2 belum ditandatangani trainer.' : 'Diperiksa – sesuai.']])->all();
        $audits->saveChecklist($auditor, $a1, $items, null, null);
        $audits->addFinding($auditor, $a1, ['type' => 'minor', 'clause' => '7.5', 'description' => 'Daftar hadir kelas P3K-B02 hari ke-2 belum ditandatangani trainer.']);
        $audits->addFinding($auditor, $a1, ['type' => 'peluang', 'clause' => '10.3', 'description' => 'Absensi digital (QR) dapat mengurangi kelalaian tanda tangan.']);
        $audits->submitReport($auditor, $a1->fresh(), 'Proses penyelenggaraan pelatihan secara umum sesuai dan efektif. Satu ketidaksesuaian minor pada pengendalian rekaman.');

        // Tinjauan manajemen (draf, masukan otomatis) + tindak lanjut
        $reviews = app(ManagementReviewService::class);
        $mrv = $reviews->create($mr, ['meeting_date' => now()->toDateString(), 'period_from' => now()->subYear()->startOfMonth()->toDateString(),
            'period_to' => now()->subMonth()->endOfMonth()->toDateString(), 'location' => 'Ruang rapat kantor', 'attendees' => "Budi Santoso (Direktur)\nRina Wulandari (GM)\nSari Pratiwi (MR)\nAgus Setiawan (Kadiv OPS)"]);
        $reviews->addAction($mr, $mrv, ['description' => 'Terapkan absensi digital QR untuk seluruh kelas', 'pic_id' => $kadiv->id, 'due_date' => now()->addDays(45)->toDateString()]);

        // Inovasi: satu tanpa anggaran (disetujui GM, berjalan), satu dengan anggaran menunggu Direktur
        $inv = app(InnovationService::class);
        $i1 = $inv->propose($kadiv, ['title' => 'Absensi digital QR untuk kelas pelatihan', 'background' => 'Daftar hadir kertas sering tidak lengkap (temuan audit).',
            'objective' => 'Daftar hadir 100% lengkap & langsung terekap.', 'indicator' => 'Kelas dengan daftar hadir lengkap (%)', 'baseline' => '85%', 'budget' => 0,
            'division_id' => $div('OPS'), 'pic_id' => $kadiv->id, 'start_date' => now()->toDateString(), 'end_date' => now()->addMonths(2)->toDateString()]);
        $inv->decide($gm, $i1, true, null);
        $inv->start($kadiv, $i1->fresh());
        $inv->propose($cs, ['title' => 'Chatbot WhatsApp untuk jadwal pelatihan', 'background' => 'Banyak pertanyaan berulang soal jadwal.', 'objective' => 'Waktu tanggap < 5 menit.',
            'indicator' => 'Rata-rata waktu tanggap (menit)', 'baseline' => '45 menit', 'budget' => 3500000, 'division_id' => $div('CS'), 'pic_id' => $cs->id]);

        // Laporan kinerja bulan lalu (draf dengan analisis)
        $reports = app(PerformanceReportService::class);
        $r = $reports->generate($mr, 'bulanan', now()->subMonthNoOverflow()->format('Y-m'));
        $reports->saveAnalysis($mr, $r, 'Sebagian besar sasaran mutu tercapai. Kepuasan peserta stabil; satu keluhan fasilitas (AC) sudah ditindaklanjuti lewat NCR.');
    }
}
