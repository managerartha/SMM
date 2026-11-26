# Laporan Tinjauan Keamanan – Tahap 1

Tanggal: 26 Nov 2026 · Ruang lingkup: seluruh kode Tahap 1 (login & peran, dokumen terkendali, legalitas,
berkas/Google Drive, log aktivitas, backup/restore, installer & pembaruan, admin).

## Metode

1. Tinjauan manual atas setiap controller, policy, layanan, dan view.
2. 59 tes otomatis (271 asersi), termasuk hak akses unduhan per peran, CSRF, batas login, validasi isi berkas,
   log append-only, serta penomoran paralel.
3. Simulasi pemasangan dari ZIP produksi (struktur folder Hostinger): installer, akses ke `/.env`, cron, backup,
   restore ke database kosong, dan pembaruan versi.
4. `composer audit`: tidak ada kerentanan yang diketahui pada dependensi.
5. Penelusuran semua halaman dengan browser untuk tiap peran: tidak ada galat 500.

## Kepatuhan terhadap persyaratan (spesifikasi bagian 10)

| Persyaratan | Status | Implementasi |
|---|---|---|
| Login email + kata sandi, hash bawaan | ✅ | Bcrypt (Laravel/Fortify). Kata sandi minimal 10 karakter, berisi huruf + angka |
| 2FA (TOTP) opsional untuk GM, Direktur, MR | ✅ | Fortify. Diatur lewat izin `akun.2fa`, dengan kode pemulihan. GM bisa mereset 2FA pengguna |
| Batas percobaan login | ✅ | 5/menit per email+IP dan 30/jam per IP. Login gagal dicatat di log |
| Keluar otomatis saat tidak aktif | ✅ | Sesi 30 menit. "Ingat saya" dimatikan di server (`DisableRememberMe`) |
| CSRF, XSS, SQL injection | ✅ | Token CSRF di semua form (diuji). Blade meng-escape semua keluaran, dan tiga `{!! !!}` hanya berisi teks statis atau SVG QR dari Fortify. Semua query lewat Eloquent/binding (tanpa SQL mentah selain impor restore) |
| Validasi unggahan berdasarkan isi (MIME) | ✅ | `FileValidator`: finfo + magic bytes PDF, `getimagesize` untuk gambar, struktur ZIP untuk DOCX/XLSX. **DOCX/XLSX bermakro ditolak.** Nama berkas diganti UUID |
| Cek hak akses di server untuk unduhan & aksi | ✅ | `DocumentPolicy` / `ExternalDocumentPolicy` dipanggil di layanan, bukan hanya di tampilan. ID revisi dari dokumen lain ditolak (404) |
| `.env`, kredensial Google, storage tidak bisa diakses web & tidak masuk Git | ✅ | Susunan satu folder di `public_html` (atas permintaan GM): `.htaccess` mengalihkan **semua** permintaan ke `index.php` kecuali aset `build/`, favicon, robots; dotfile ditolak (403). Diuji di Apache: `/.env`, `/storage/…`, `/vendor/…`, `/composer.json`, `/artisan` → 403/404. Token Google dienkripsi (APP_KEY) dengan izin 0600. `.gitignore` mencakup semuanya |
| Berkas di luar folder publik, unduh hanya lewat aplikasi | ✅ | Berkas di Google Drive (tidak pernah dibagikan) atau sementara di `storage/app/private`. Unduhan dikirim sebagai *attachment* dengan `nosniff` dan `no-store` |
| Akun dinonaktifkan, bukan dihapus | ✅ | Tidak ada rute hapus pengguna (diuji). Sesi pengguna nonaktif atau auditor kedaluwarsa langsung diputus |
| Log aktivitas append-only | ✅ | Model menolak update/delete. Rantai hash SHA-256 mendeteksi perubahan langsung di database. Diperiksa otomatis tiap Senin, dengan email ke GM bila tidak utuh |
| Tidak menyentuh folder aplikasi lain | ✅ | Semua jalur relatif terhadap folder aplikasi (`storage_path`). Database dan user database terpisah dari LMS/leads |
| Header keamanan | ✅ | CSP, X-Frame-Options DENY, nosniff, Referrer-Policy, HSTS (HTTPS), dan pengalihan paksa ke HTTPS |

## Temuan yang ditemukan dan sudah diperbaiki selama tinjauan

| # | Temuan | Dampak | Perbaikan |
|---|---|---|---|
| F1 | Pembuatan penghitung nomor dan `SELECT … FOR UPDATE` dalam satu transaksi menyebabkan **deadlock** saat 6 proses menyimpan bersamaan (ditemukan oleh tes paralel) | Simpan gagal saat ramai | Baris penghitung dibuat lewat koneksi terpisah, dan isolasi database diatur ke READ COMMITTED (tanpa gap lock). Tes paralel lolos berulang kali |
| F2 | "Ingat saya" membuat pengguna tetap login melewati batas tidak aktif | Sesi di perangkat bersama tidak berakhir | Opsi dihapus dari tampilan dan diabaikan di server |
| F3 | `TRUSTED_PROXIES` dibaca dengan `env()` sehingga tidak berlaku saat config di-cache | IP di log bisa salah | Dipindah ke `config/smm.php` dengan middleware sendiri |
| F4 | Pemaksaan HTTPS bergantung pada `APP_ENV`, bukan pada URL | Form gagal bila HTTPS belum siap | HTTPS dipaksa bila `APP_URL` diawali `https://` |
| F5 | Auditor mendapat 403 pada dokumen yang belum berlaku, padahal tautannya tampil | Inkonsistensi akses | Peran "lihat semua" bisa melihat halaman info. Draf tetap tidak bisa diunduh |
| F6 | Permintaan ke Drive diulang 3× juga untuk 404/403 | Lambat, tidak perlu | Pengulangan hanya untuk galat jaringan, 429, dan 5xx |

## Risiko sisa dan rekomendasi

| # | Risiko | Tingkat | Rekomendasi |
|---|---|---|---|
| R0 | Kode aplikasi berada di dalam `public_html` (susunan satu folder). Keamanan bergantung pada `.htaccess`; bila `.htaccess` terhapus/tidak terbaca, berkas seperti `composer.json` bisa terbuka | Rendah–Sedang | `.env` & dotfile tetap ditolak oleh `<FilesMatch>`; berkas unggahan hanya sementara sebelum pindah ke Drive. Setelah pembaruan, cek `https://mutu…/.env` → harus 403 |
| R1 | SMM, LMS, dan sistem leads berjalan sebagai **user Linux yang sama** di satu akun hosting. Bila salah satu aplikasi dibobol, penyerang bisa membaca berkas aplikasi lain, termasuk `.env` SMM | Sedang | User database sudah terpisah. Jaga LMS & leads tetap mutakhir dan tidak memakai kata sandi yang sama. Jangka panjang: pisahkan SMM ke akun hosting/VPS sendiri |
| R2 | Pemilik akses database (hPanel/phpMyAdmin) bisa mengubah tabel secara langsung | Rendah–Sedang | Rantai hash mendeteksi perubahan pada log aktivitas (cek mingguan + email). Batasi siapa yang memegang akses hPanel, dan aktifkan 2FA di akun Hostinger |
| R3 | Data utama dan backup berada di satu akun Google | Sedang | Aktifkan 2FA di akun Gmail dan pasang Google Drive for Desktop (mirror) di PC kantor sebagai salinan kedua |
| R4 | Kata sandi backup dan token Drive ada di server yang sama | Rendah–Sedang | Scope `drive.file` membatasi token hanya ke berkas buatan aplikasi. Bila server diduga dibobol: putuskan Drive di Admin, cabut akses aplikasi di akun Google, ganti `SMM_BACKUP_PASSWORD` dan `APP_KEY` |
| R5 | CSP masih mengizinkan `unsafe-inline`/`unsafe-eval` (dibutuhkan Alpine.js & dialog konfirmasi) | Rendah | Semua keluaran sudah di-escape. Tahap berikut bisa beralih ke build Alpine CSP |
| R6 | Form "Lupa kata sandi" hanya dibatasi 1 email/menit per akun | Rendah | Pesan dibuat seragam untuk email terdaftar maupun tidak. Bisa ditambah rate-limit per IP bila disalahgunakan |
| R7 | 2FA opsional sesuai spesifikasi | Rendah | **Sangat disarankan** diaktifkan untuk GM, Direktur, dan MR sejak hari pertama |
| R8 | FPDI versi gratis tidak bisa membaca sebagian PDF modern. `qpdf` kemungkinan tidak tersedia di Hostinger | Info | PDF seperti itu ditolak saat unggah, dengan petunjuk untuk menyimpan ulang sebagai PDF/A. Tidak pernah ada salinan lama yang lolos tanpa tanda air |
| R9 | Aplikasi OAuth Google tidak diverifikasi (muncul peringatan saat menghubungkan) | Info | Wajar untuk aplikasi internal dengan scope non-sensitif. Lihat `docs/GOOGLE_DRIVE.md` |

## Belum diuji di lingkungan sungguhan

Yang berikut hanya bisa dilakukan setelah aplikasi terpasang di Hostinger dengan kredensial asli:

- Unggah, unduh, dan backup ke **Google Drive sungguhan**. Kode REST-nya sudah diuji dengan respons tiruan, dan alurnya diuji dengan penyimpanan lokal yang meniru struktur Drive.
- **Restore dari Drive sungguhan** (alur yang sama sudah diuji dari Drive tiruan dan dari ZIP ke database kosong).
- Pengiriman email lewat **SMTP Hostinger**.
- Jalur PHP cron di Hostinger (`/opt/alt/php83/usr/bin/php`).

Langkah ujinya ada di `docs/DEPLOY_HOSTINGER.md` Langkah 9 dan `docs/RESTORE.md`.

## Tambahan Tahap 2 (NCR & CAPA, Keluhan, Risiko)

- Hak akses lewat policy di server: `ComplaintPolicy`, `NcrPolicy`, `RiskPolicy` (diuji: staf hanya melihat keluhan/NCR
  miliknya atau yang ditugaskan; auditor baca saja; kadiv hanya divisinya).
- **Pemisahan tugas**: penanggung jawab NCR tidak bisa memverifikasi pelaksanaan/efektivitas tindakannya sendiri;
  penutupan mengikuti Matriks Pengesahan.
- Lampiran (keluhan, bukti tindakan & efektivitas) disimpan di Drive dan diunduh lewat `/lampiran/{uuid}` dengan cek
  hak akses ke objek induknya; UUID acak, tanpa tautan langsung.
- Semua aksi tercatat di log aktivitas append-only dan riwayat NCR.
- Izin baru ditambahkan ke peran bawaan saat pembaruan tanpa menimpa penyesuaian GM (diuji).

## Tambahan Tahap 3 (LMS, Catatan Mutu, Personel, Sarana, Survei, KPI, Pemasok)

- **Data pribadi peserta tidak disalin**: impor survei/kelas hanya menyimpan angka rekap, skor, aspek, dan komentar;
  kolom nama/HP/email/NIK/alamat diabaikan (diuji: tidak ada kolom pribadi di tabel, nama tidak tersimpan).
- Adapter LMS **baca-saja**; token API disimpan di `.env`, dikirim lewat header `Authorization`, bukan URL.
- Impor dibatasi ke CSV/XLSX maks. 10 MB dan izin `lms.impor`; baris salah dilewati dan dilaporkan, tidak menggagalkan impor.
- Berkas personel (KTP, ijazah, kontrak) hanya bisa dilihat pemegang izin `personel.lihat` dan diunduh lewat aplikasi.
- Catatan mutu disaring per divisi; lampiran dicek hak aksesnya ke objek induk.
- Temuan saat pengujian & sudah diperbaiki: tipe `personnel_document` belum terdaftar sehingga pengingat masa berlaku
  berkas personel akan gagal di cron (ditemukan oleh tes, sekarang tercakup).

## Tambahan Tahap 4 (Audit, Tinjauan Manajemen, Inovasi, Laporan, Paket Bukti)

- **Independensi auditor** diperiksa di server: auditor tidak boleh mengaudit divisinya sendiri, dan auditor tidak bisa mengesahkan laporan auditnya sendiri. Keduanya diuji.
- Semua pengesahan memakai Matriks Pengesahan di sisi server, termasuk inovasi yang pengesahnya ditentukan dari anggaran. Nama, jabatan, dan waktu pengesah disimpan dan dicetak di PDF.
- PDF dibuat lokal dengan dompdf. **Sumber remote dimatikan** dan akses berkas dibatasi ke folder aplikasi (chroot), sehingga isi teks dari pengguna tidak bisa memuat berkas atau URL luar.
- Paket Bukti hanya bisa dibuat oleh pemegang `paket.buat` dan diunduh oleh pemegang `paket.unduh`, lewat `/lampiran/{uuid}` dengan cek hak akses.
  - Dokumen terkendali di dalam paket selalu diberi tanda air.
  - Data pribadi peserta tetap tidak ada, karena paket hanya memakai data yang sudah ada di SMM.
- Pembuatan paket berjalan di antrian dengan batas waktu dan satu paket dalam satu waktu, supaya hosting tidak terbebani.
- Temuan saat pengujian dan sudah diperbaiki: kolom peserta tinjauan wajib di database, sehingga pembuatan tinjauan tanpa peserta gagal (500). Sekarang kolom itu opsional saat dibuat dan wajib saat notulen disimpan.
