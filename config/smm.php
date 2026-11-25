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
    ],

    'role_permissions' => [
        'gm' => ['*'],
        'direktur' => [
            'dashboard.lihat', 'dokumen.lihat', 'dokumen.lihat-semua', 'dokumen.kelola', 'dokumen.review',
            'dokumen.sahkan', 'dokumen.unduh-kedaluwarsa', 'dokumen.unduh-sumber', 'legalitas.lihat',
            'log.lihat', 'akun.2fa',
        ],
        'mr' => [
            'dashboard.lihat', 'dokumen.lihat', 'dokumen.lihat-semua', 'dokumen.kelola', 'dokumen.review',
            'dokumen.sahkan', 'dokumen.unduh-kedaluwarsa', 'dokumen.unduh-sumber', 'dokumen.arsip',
            'legalitas.lihat', 'log.lihat', 'master.kelola', 'akun.2fa',
        ],
        'kadiv' => [
            'dashboard.lihat', 'dokumen.lihat', 'dokumen.lihat-semua', 'dokumen.kelola-divisi', 'dokumen.review',
            'legalitas.lihat',
        ],
        'staf' => ['dashboard.lihat', 'dokumen.lihat'],
        'trainer' => ['dashboard.lihat', 'dokumen.lihat'],
        'auditor' => [
            'dashboard.lihat', 'dokumen.lihat', 'dokumen.lihat-semua', 'dokumen.unduh-kedaluwarsa',
            'legalitas.lihat', 'log.lihat',
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
