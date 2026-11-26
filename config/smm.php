<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi SMM
|--------------------------------------------------------------------------
| Nilai di sini adalah BAWAAN. Yang bisa diubah dari layar Admin → Pengaturan
| disimpan di tabel `settings` dan menimpa nilai bawaan (lihat App\Support\Settings).
*/

return [

    'install_token' => env('SMM_INSTALL_TOKEN'),

    // Isi hanya bila situs berada di belakang CDN/proxy (mis. '*' atau daftar IP), supaya IP pengguna di log benar.
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

    'drive' => [
        'driver' => env('SMM_DRIVE_DRIVER', 'local'), // local | google
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'root' => env('SMM_DRIVE_ROOT', 'SMM'),
        'backup_root' => env('SMM_DRIVE_BACKUP_ROOT', 'Backup SMM'),
        'local_root' => storage_path('app/drive-local'),
        'token_path' => storage_path('app/google/token.json'),
    ],

    'backup' => [
        'password' => env('SMM_BACKUP_PASSWORD'),
    ],

    // Adapter LMS (Tahap 3). API baca-saja diaktifkan bila LMS sudah menyediakan endpoint.
    'lms' => [
        'api_url' => env('SMM_LMS_API_URL'),
        'api_token' => env('SMM_LMS_API_TOKEN'),
    ],

    'whatsapp' => [
        'enabled' => env('SMM_WHATSAPP_ENABLED', false),
    ],

    // Nilai bawaan pengaturan yang bisa diubah GM.
    'defaults' => [
        'upload.max_mb' => 20,
        'upload.allowed' => ['pdf', 'docx', 'xlsx', 'jpg', 'png'],
        'numbering.document' => 'ASI-{jenis}-{divisi}-{urut:2}',
        'numbering.revision' => 'Rev. {urut:2}',
        'numbering.external_document' => 'DE-{kategori}-{urut:3}',
        'numbering.record' => '{formulir}/{tahun}/{urut:4}',
        'numbering.ncr' => 'NCR/{tahun}/{urut:3}',
        'numbering.complaint' => 'KLH/{tahun}/{urut:3}',
        'numbering.audit' => 'AI/{tahun}/{urut:2}',
        'numbering.personnel_ta' => 'TA-{urut:3}',
        'numbering.personnel_tt' => 'TT-{urut:3}',
        'numbering.facility' => 'SP-{kategori}-{urut:3}',
        'numbering.innovation' => 'INV/{tahun}/{urut:2}',
        'numbering.performance_report' => 'LKL/{tahun}/{bulan}',
        'reminder.external_days' => [90, 60, 30],
        'reminder.external_soon_days' => 90,
        'reminder.review_days' => 30,
        'watermark.controlled_copy' => true,
        'backup.daily_retention' => 30,
        'backup.monthly_retention' => 12,
        'numbering.risk' => 'RP-{urut:3}',
        'complaint.response_days' => 3,
        'capa.reminder_days' => 3,
        'capa.effectiveness_days' => [30, 60, 90],
        'risk.review_months' => 12,
        'numbering.supplier' => 'PMS-{urut:3}',
        'numbering.kpi' => 'KPI-{urut:2}',
        'record.retention_years' => 5,
        'survey.negative_threshold' => 2.5,
        'reminder.personnel_days' => [90, 60, 30],
        'reminder.facility_days' => 14,
    ],

    'facility_categories' => [
        'ALT' => 'Alat peraga & praktik', 'APD' => 'Alat pelindung diri', 'ELK' => 'Elektronik & IT',
        'RUA' => 'Ruangan & bangunan', 'PRB' => 'Perabot', 'KND' => 'Kendaraan', 'LNY' => 'Lainnya',
    ],

    // Peran → label tampilan.
    'roles' => [
        'gm' => 'General Manager',
        'direktur' => 'Direktur',
        'mr' => 'Wakil Manajemen Mutu (MR)',
        'kadiv' => 'Kepala Divisi / Koordinator',
        'staf' => 'Staf',
        'trainer' => 'Trainer',
        'auditor' => 'Auditor',
    ],

    // Izin → label. Dipetakan ke peran di 'role_permissions' (bisa diubah di layar Admin → Peran & Izin).
    'permissions' => [
        'dashboard.lihat' => 'Melihat dashboard',
        'dokumen.lihat' => 'Melihat & mengunduh dokumen berlaku sesuai cakupan akses',
        'dokumen.lihat-semua' => 'Melihat & mengunduh semua dokumen berlaku',
        'dokumen.kelola' => 'Membuat & merevisi dokumen semua divisi',
        'dokumen.kelola-divisi' => 'Membuat & merevisi dokumen divisinya',
        'dokumen.review' => 'Mereviu (paraf) dokumen',
        'dokumen.sahkan' => 'Mengesahkan dokumen (sesuai matriks pengesahan)',
        'dokumen.unduh-kedaluwarsa' => 'Mengunduh revisi kedaluwarsa (bertanda TIDAK TERKENDALI)',
        'dokumen.unduh-sumber' => 'Mengunduh file sumber (DOCX/XLSX)',
        'dokumen.arsip' => 'Mengarsipkan dokumen',
        'legalitas.lihat' => 'Melihat legalitas & kontrak',
        'legalitas.kelola' => 'Menambah / memperpanjang legalitas & kontrak',
        'legalitas.verifikasi' => 'Memverifikasi keaslian dokumen eksternal',
        'log.lihat' => 'Melihat log aktivitas',
        'pengguna.kelola' => 'Mengelola pengguna',
        'master.kelola' => 'Mengelola data master (divisi, jenis dokumen, kategori)',
        'pengaturan.kelola' => 'Mengelola pengaturan, matriks pengesahan, peran & izin, pembaruan sistem',
        'backup.kelola' => 'Melihat & menjalankan backup, menerima email kegagalan backup',
        'akun.2fa' => 'Boleh mengaktifkan verifikasi dua langkah',
        // Tahap 2
        'keluhan.catat' => 'Mencatat keluhan / feedback / saran',
        'keluhan.lihat-semua' => 'Melihat semua keluhan & feedback',
        'keluhan.kelola' => 'Menangani keluhan semua divisi (tugaskan, tanggapi, tutup, jadikan NCR)',
        'keluhan.kelola-divisi' => 'Menangani keluhan divisinya',
        'ncr.lihat-semua' => 'Melihat semua NCR & CAPA',
        'ncr.catat' => 'Membuat NCR',
        'ncr.kelola' => 'Mengelola NCR semua divisi (akar masalah, tindakan)',
        'ncr.kelola-divisi' => 'Mengelola NCR divisinya',
        'ncr.verifikasi' => 'Memverifikasi pelaksanaan & efektivitas tindakan korektif',
        'ncr.tutup' => 'Menutup NCR (sesuai matriks pengesahan)',
        'risiko.lihat' => 'Melihat register risiko & peluang',
        'risiko.kelola' => 'Mengelola risiko & peluang semua divisi',
        'risiko.kelola-divisi' => 'Mengelola risiko & peluang divisinya',
        // Tahap 3
        'kelas.lihat' => 'Melihat daftar kelas (dari LMS)',
        'lms.impor' => 'Mengimpor data kelas & survei dari LMS',
        'catatan.lihat-semua' => 'Melihat semua catatan mutu',
        'catatan.catat' => 'Mengunggah catatan mutu (bukti kegiatan)',
        'personel.lihat' => 'Melihat data personel (TA/TT)',
        'personel.kelola' => 'Mengelola data & berkas personel',
        'sarana.lihat' => 'Melihat sarana & prasarana',
        'sarana.kelola' => 'Mengelola sarana & prasarana',
        'survei.lihat' => 'Melihat rekap survei',
        'kpi.lihat' => 'Melihat sasaran mutu & KPI',
        'kpi.kelola' => 'Mengelola sasaran mutu & KPI',
        'kpi.isi' => 'Mengisi realisasi KPI divisinya',
        'pemasok.lihat' => 'Melihat data pemasok',
        'pemasok.kelola' => 'Mengelola & mengevaluasi pemasok',
    ],

    'role_permissions' => [
        'gm' => ['*'],
        'direktur' => [
            'dashboard.lihat', 'dokumen.lihat', 'dokumen.lihat-semua', 'dokumen.kelola', 'dokumen.review',
            'dokumen.sahkan', 'dokumen.unduh-kedaluwarsa', 'dokumen.unduh-sumber', 'legalitas.lihat',
            'log.lihat', 'akun.2fa',
            'keluhan.catat', 'keluhan.lihat-semua', 'ncr.lihat-semua', 'ncr.catat', 'ncr.tutup', 'risiko.lihat',
            'kelas.lihat', 'catatan.lihat-semua', 'personel.lihat', 'sarana.lihat', 'survei.lihat', 'kpi.lihat', 'pemasok.lihat',
        ],
        'mr' => [
            'dashboard.lihat', 'dokumen.lihat', 'dokumen.lihat-semua', 'dokumen.kelola', 'dokumen.review',
            'dokumen.sahkan', 'dokumen.unduh-kedaluwarsa', 'dokumen.unduh-sumber', 'dokumen.arsip',
            'legalitas.lihat', 'log.lihat', 'master.kelola', 'akun.2fa',
            'keluhan.catat', 'keluhan.lihat-semua', 'keluhan.kelola', 'ncr.lihat-semua', 'ncr.catat', 'ncr.kelola',
            'ncr.verifikasi', 'ncr.tutup', 'risiko.lihat', 'risiko.kelola',
            'kelas.lihat', 'lms.impor', 'catatan.lihat-semua', 'catatan.catat', 'personel.lihat', 'personel.kelola',
            'sarana.lihat', 'sarana.kelola', 'survei.lihat', 'kpi.lihat', 'kpi.kelola', 'kpi.isi', 'pemasok.lihat', 'pemasok.kelola',
        ],
        'kadiv' => [
            'dashboard.lihat', 'dokumen.lihat', 'dokumen.lihat-semua', 'dokumen.kelola-divisi', 'dokumen.review',
            'legalitas.lihat',
            'keluhan.catat', 'keluhan.lihat-semua', 'keluhan.kelola-divisi', 'ncr.lihat-semua', 'ncr.catat', 'ncr.kelola-divisi',
            'risiko.lihat', 'risiko.kelola-divisi',
            'kelas.lihat', 'catatan.lihat-semua', 'catatan.catat', 'personel.lihat', 'sarana.lihat', 'sarana.kelola',
            'survei.lihat', 'kpi.lihat', 'kpi.isi', 'pemasok.lihat',
        ],
        'staf' => ['dashboard.lihat', 'dokumen.lihat', 'keluhan.catat', 'kelas.lihat', 'catatan.catat', 'sarana.lihat'],
        'trainer' => ['dashboard.lihat', 'dokumen.lihat', 'keluhan.catat', 'kelas.lihat', 'catatan.catat'],
        'auditor' => [
            'dashboard.lihat', 'dokumen.lihat', 'dokumen.lihat-semua', 'dokumen.unduh-kedaluwarsa',
            'legalitas.lihat', 'log.lihat',
            'keluhan.lihat-semua', 'ncr.lihat-semua', 'risiko.lihat',
            'kelas.lihat', 'catatan.lihat-semua', 'personel.lihat', 'sarana.lihat', 'survei.lihat', 'kpi.lihat', 'pemasok.lihat',
        ],
    ],

    'divisions' => [
        'QA' => 'Quality Assurance / Mutu',
        'OPS' => 'Operasional Pelatihan',
        'CS' => 'Customer Service',
        'MKT' => 'Marketing',
        'ADM' => 'Administrasi',
        'HR' => 'Sumber Daya Manusia',
        'PRC' => 'Pengadaan',
        'FIN' => 'Keuangan',
    ],

    'evidence_categories' => [
        1 => ['SKP PJK3', '01_SKP_PJK3'],
        2 => ['Akta dan struktur organisasi', '02_Akta_Struktur_Organisasi'],
        3 => ['Berkas Tenaga Ahli (TA)', '03_Tenaga_Ahli'],
        4 => ['Berkas Tenaga Teknis', '04_Tenaga_Teknis'],
        5 => ['Surat sewa, domisili, SPK', '05_Sewa_Domisili_SPK'],
        6 => ['Sarana dan prasarana', '06_Sarana_Prasarana'],
        7 => ['SOP layanan K3', '07_SOP_Layanan_K3'],
        8 => ['Bukti berjalannya SOP layanan K3', '08_Bukti_SOP_Berjalan'],
        9 => ['Laporan pencapaian target dan realisasi kinerja layanan', '09_Laporan_Kinerja_Layanan'],
        10 => ['Hasil survei pengguna layanan K3', '10_Hasil_Survei'],
        11 => ['SOP penanganan keluhan dan feedback layanan K3', '11_SOP_Keluhan_Feedback'],
        12 => ['Dokumentasi program dan inovasi peningkatan kualitas mutu layanan', '12_Program_Inovasi'],
        13 => ['Dokumen perencanaan audit internal dan laporan hasil audit', '13_Audit_Internal'],
        14 => ['Rekaman tindakan korektif (corrective action)', '14_Tindakan_Korektif'],
        15 => ['Laporan pemantauan efektivitas perbaikan', '15_Efektivitas_Perbaikan'],
        16 => ['Sistem dan laporan pengendalian mutu berbasis digital', '16_Sistem_Digital'],
    ],

    // [kode, nama, review wajib, interval tinjauan (bulan), kategori bukti bawaan]
    'document_types' => [
        ['MM', 'Manual Mutu', true, 12, 16],
        ['KB', 'Kebijakan & Sasaran Mutu', true, 12, 16],
        ['SOP', 'Standard Operating Procedure', true, 12, 7],
        ['IK', 'Instruksi Kerja', true, 12, 7],
        ['F', 'Formulir', false, 12, 8],
        ['STR', 'Struktur Organisasi', true, 12, 2],
    ],

    // [kode, nama, kategori bukti bawaan]
    'external_categories' => [
        ['SKP', 'SKP PJK3', 1],
        ['AKT', 'Akta Pendirian', 2],
        ['AKP', 'Akta Perubahan', 2],
        ['NIB', 'NIB / Perizinan', 1],
        ['DOM', 'Surat Domisili', 5],
        ['SWA', 'Surat Sewa', 5],
        ['SPK', 'SPK / Kontrak Klien', 5],
        ['LNY', 'Lainnya', null],
    ],

    // Matriks pengesahan: [subject_key, label, peran pengesah, mode, tahap]
    'approval_rules' => [
        ['document:MM', 'Manual Mutu', 'direktur', 'approve', 1],
        ['document:KB', 'Kebijakan & sasaran mutu', 'direktur', 'approve', 1],
        ['document:SOP', 'SOP', 'direktur', 'approve', 1],
        ['document:STR', 'Struktur organisasi', 'direktur', 'approve', 1],
        ['document:IK', 'Instruksi kerja', 'gm', 'approve', 1],
        ['document:F', 'Formulir', 'gm', 'approve', 1],
        ['external_document', 'Dokumen eksternal (verifikasi keaslian)', 'gm', 'verify', 1],
        ['capa_closure', 'Penutupan CAPA', 'gm', 'approve', 2],
        ['effectiveness_report', 'Laporan efektivitas', 'gm', 'approve', 2],
        ['performance_report_monthly', 'Laporan kinerja bulanan', 'gm', 'approve', 3],
        ['performance_report_annual', 'Laporan kinerja tahunan', 'direktur', 'approve', 3],
        ['audit_program_annual', 'Program audit tahunan', 'direktur', 'approve', 4],
        ['audit_report_process', 'Laporan audit per proses', 'gm', 'approve', 4],
        ['management_review_minutes', 'Notulen Tinjauan Manajemen', 'direktur', 'approve', 4],
        ['innovation_no_budget', 'Program inovasi tanpa anggaran', 'gm', 'approve', 4],
    ],
];
