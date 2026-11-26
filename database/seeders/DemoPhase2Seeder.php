<?php

namespace Database\Seeders;

use App\Domain\Capa\CapaWorkflow;
use App\Domain\Complaints\ComplaintService;
use App\Domain\Risks\RiskService;
use App\Models\Complaint;
use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Data contoh Tahap 2 (lokal saja): keluhan, NCR dalam pemantauan, risiko & peluang. */
class DemoPhase2Seeder extends Seeder
{
    public function run(): void
    {
        if (Complaint::exists()) {
            return;
        }
        config(['queue.default' => 'sync']);
        $u = fn (string $email) => User::where('email', $email)->firstOrFail();
        $div = fn (string $code) => Division::where('code', $code)->value('id');
        $gm = $u('gm@contoh.test');
        $mr = $u('mr@contoh.test');
        $kadiv = $u('kadiv.ops@contoh.test');
        $cs = $u('cs@contoh.test');
        $trainer = $u('trainer@contoh.test');

        $svc = app(ComplaintService::class);
        $fb = $svc->create($cs, [
            'type' => 'feedback', 'source' => 'peserta', 'channel' => 'whatsapp', 'received_at' => now()->subDays(10)->toDateString(),
            'reporter_name' => 'Andi Pratama', 'reporter_org' => 'PT Maju Konstruksi', 'class_code' => 'AK3U-2026-11',
            'category' => 'trainer', 'division_id' => $div('OPS'), 'subject' => 'Apresiasi materi praktik APAR',
            'description' => 'Peserta menyampaikan materi praktik APAR sangat jelas dan aplikatif.',
        ]);
        $svc->respond($gm, $fb, 'Terima kasih atas apresiasinya, kami teruskan kepada trainer.', 'Diteruskan ke trainer sebagai umpan balik positif.');

        $klh = $svc->create($cs, [
            'type' => 'keluhan', 'source' => 'klien', 'channel' => 'email', 'received_at' => now()->subDays(40)->toDateString(),
            'reporter_name' => 'Ibu Sinta (HRD)', 'reporter_org' => 'PT Sentosa Logistik', 'class_code' => 'AK3U-2026-09',
            'category' => 'sertifikat', 'division_id' => $div('ADM'), 'subject' => 'Sertifikat peserta terlambat dikirim 3 minggu',
            'description' => 'Klien mengeluhkan sertifikat 12 peserta baru diterima 3 minggu setelah kelas selesai.', 'severity' => 'tinggi',
        ]);
        $svc->assign($gm, $klh, $mr->id);
        $ncr = $svc->escalate($gm, $klh, [
            'title' => 'Keterlambatan pengiriman sertifikat', 'division_id' => $div('ADM'), 'pic_id' => $kadiv->id,
            'severity' => 'mayor', 'target_date' => now()->subDays(5)->toDateString(),
        ]);
        $svc->respond($mr, $klh->fresh(), 'Mohon maaf atas keterlambatan. Sertifikat sudah dikirim dan proses kami perbaiki.', 'Ditindaklanjuti melalui '.$ncr->number);

        $wf = app(CapaWorkflow::class);
        $wf->recordAnalysis($kadiv, $ncr, 'Sertifikat dikirim manual via kurir ekspres.', '5why',
            "1. Sertifikat terlambat karena menunggu tanda tangan basah.\n2. Penandatangan sedang dinas luar.\n3. Tidak ada penandatangan pengganti.\n→ Akar masalah: SOP penerbitan sertifikat tidak mengatur penandatangan pengganti & batas waktu.");
        $a1 = $wf->addAction($kadiv, $ncr, 'Revisi SOP penerbitan sertifikat: tambah penandatangan pengganti dan batas waktu maksimal 7 hari kerja.', $mr->id, now()->subDays(20)->toDateString());
        $a2 = $wf->addAction($kadiv, $ncr, 'Pantau waktu penerbitan sertifikat setiap kelas di rekap bulanan.', $kadiv->id, now()->subDays(20)->toDateString());
        $wf->startActions($kadiv, $ncr->fresh());
        $wf->completeAction($mr, $a1->fresh(), 'SOP direvisi dan disosialisasikan pada rapat bulanan.');
        $wf->completeAction($kadiv, $a2->fresh(), 'Kolom tanggal kirim sertifikat ditambahkan pada rekap kelas.');
        $wf->verify($gm, $ncr->fresh(), 'Revisi SOP & rekap sudah diperiksa, sesuai rencana.');

        // Contoh NCR baru yang masih terbuka
        $wf->create($mr, [
            'source' => 'temuan_internal', 'title' => 'Daftar hadir kelas tidak ditandatangani trainer',
            'description' => 'Pada 2 kelas bulan ini daftar hadir tidak ditandatangani trainer sesuai formulir ASI-F-OPS-01.',
            'requirement' => 'ASI-F-OPS-01', 'division_id' => $div('OPS'), 'severity' => 'minor',
            'found_at' => now()->subDays(2)->toDateString(), 'pic_id' => $kadiv->id, 'target_date' => now()->addDays(14)->toDateString(),
        ]);

        $saran = $svc->create($trainer, [
            'type' => 'saran', 'source' => 'internal', 'received_at' => now()->subDay()->toDateString(), 'division_id' => $div('OPS'),
            'category' => 'sarana', 'subject' => 'Usulan penambahan manekin CPR', 'description' => 'Jumlah manekin CPR kurang untuk kelas > 20 peserta.',
        ]);

        $rs = app(RiskService::class);
        $rs->create($gm, ['type' => 'risiko', 'division_id' => $div('QA'), 'process' => 'Perizinan PJK3', 'title' => 'SKP PJK3 kedaluwarsa sebelum perpanjangan selesai',
            'cause' => 'Proses perpanjangan terlambat diajukan', 'consequence' => 'Tidak bisa menyelenggarakan pelatihan resmi', 'likelihood' => 2, 'impact' => 5,
            'existing_controls' => 'Pengingat otomatis 90/60/30 hari di SMM', 'treatment' => 'Ajukan perpanjangan 120 hari sebelum berakhir', 'treatment_pic_id' => $gm->id, 'owner_id' => $gm->id]);
        $rs->create($kadiv, ['type' => 'risiko', 'division_id' => $div('OPS'), 'process' => 'Penyelenggaraan kelas', 'title' => 'Trainer berhalangan mendadak',
            'cause' => 'Trainer sakit / tugas lain', 'consequence' => 'Kelas tertunda, keluhan klien', 'likelihood' => 3, 'impact' => 4,
            'existing_controls' => 'Daftar trainer cadangan', 'treatment' => 'Pastikan minimal 2 trainer bersertifikat per program', 'owner_id' => $kadiv->id]);
        $rs->create($gm, ['type' => 'peluang', 'division_id' => $div('MKT'), 'process' => 'Pemasaran', 'title' => 'Kelas inhouse untuk klien kontraktor migas',
            'consequence' => 'Pendapatan & reputasi naik', 'likelihood' => 3, 'impact' => 3, 'treatment' => 'Siapkan paket inhouse & proposal', 'owner_id' => $gm->id]);
    }
}
