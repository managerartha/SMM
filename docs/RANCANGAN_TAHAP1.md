# Rancangan Tahap 1 — SMM PT Artha Safety Indonesia

Dokumen ini mencatat keputusan rancangan yang sudah disetujui GM (25 Nov 2026).

## Keputusan utama

| Topik | Keputusan |
|---|---|
| Stack | Laravel 12, PHP 8.2+, MySQL/MariaDB, Blade + Tailwind + Alpine (aset di-build sebelum ZIP) |
| LMS | PHP murni + MySQL (repo `managerartha/LMS`). Kelas = **batch**, kode = `kode_batch`. SMM hanya membaca lewat adapter (Tahap 3) |
| Deploy | ZIP diekstrak ke `public_html` + installer web yang menulis `.env` sendiri; pembaruan lewat Admin → Pembaruan Sistem (tanpa SSH) |
| Domain | `mutu.arthasafetyindonesia.com`, website PHP sendiri di hPanel. Revisi 26 Nov 2026 atas permintaan GM: susunan **satu folder** di `public_html` (seperti LMS) supaya pemasangan sederhana; semua permintaan web dialihkan ke `index.php` sehingga berkas aplikasi tidak bisa dibuka langsung |
| Penyimpanan berkas | **Google Drive** akun `info.arthasafetyindonesia@gmail.com` (OAuth, scope `drive.file`). Unggahan disimpan sementara di server, dipindah ke Drive oleh antrian, lalu salinan lokal dihapus setelah checksum cocok |
| Unduhan | Selalu lewat aplikasi (cek hak akses); file Drive tidak pernah dibagikan |
| Tanda air | Revisi lama: "TIDAK TERKENDALI". Revisi berlaku: "SALINAN TERKENDALI – nama – tanggal" (bisa dimatikan) |
| File dokumen | PDF wajib per revisi; file sumber (DOCX/XLSX) opsional, hanya GM/MR/Direktur |
| Review | Satu pereviu dipilih saat pengajuan; wajib/tidaknya diatur per jenis dokumen |
| Pengesahan sendiri | Boleh, asal sudah direviu orang lain |
| Cakupan akses | Per dokumen: Umum atau terbatas (divisi/peran) |
| Peran | Satu pengguna boleh lebih dari satu peran |
| Kode dokumen eksternal | SKP, AKT, AKP, NIB, DOM, SWA, SPK, LNY → `DE-SKP-001` |
| Backup | Harian 01.00 WIB & bulanan: dump DB terenkripsi (ZIP AES-256) + manifest berkas → `Backup SMM/Harian` (30) dan `Backup SMM/Bulanan` (12). Berkas tidak disalin ulang karena immutable di Drive |
| Salinan kedua | Disarankan Google Drive for Desktop di PC kantor + 2FA akun Gmail |

## Prinsip

- Berkas tidak pernah ditimpa/dihapus: setiap revisi/versi = file baru.
- Soft delete (arsip/nonaktif) di semua data.
- Nomor dibuat di transaksi dengan `SELECT … FOR UPDATE` (isolasi READ COMMITTED) + tabel `issued_numbers` unik.
- Log aktivitas append-only dengan rantai hash (`php artisan smm:verify-log`).
- Matriks pengesahan generik (`approval_rules.subject_key`) supaya Tahap 2–4 tinggal menambah baris.

## Skema (ringkas)

Lihat `database/migrations/`. Tabel inti: `users`, `divisions`, `roles/permissions`, `settings`,
`document_types`, `approval_rules`, `evidence_categories`, `number_sequences`, `issued_numbers`,
`files`, `documents`, `document_access`, `document_revisions`, `document_revision_events`,
`external_document_categories`, `external_documents`, `external_document_versions`,
`reminder_logs`, `notifications`, `activity_logs`, `backup_runs`.

## Urutan pembangunan

1. Kerangka, login, 2FA, peran, seeder
2. Master data & pengaturan
3. Penomoran
4. Berkas (Drive) & unduhan aman, log aktivitas
5. Dokumen Terkendali
6. Legalitas & Kontrak + pengingat
7. Dashboard, pencarian, ekspor Excel
8. Backup & restore Google Drive
9. Panduan & tinjauan keamanan
