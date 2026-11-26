# SMM – Sistem Manajemen Mutu PT Artha Safety Indonesia

Aplikasi web untuk administrasi dan dokumentasi mutu (ISO 9001:2015 & persyaratan penilaian PJK3).
Laravel 12 · PHP 8.3 · MySQL/MariaDB · berkas disimpan di Google Drive.

| Dokumen | Isi |
|---|---|
| [docs/RANCANGAN_TAHAP1.md](docs/RANCANGAN_TAHAP1.md) | Keputusan rancangan yang disetujui |
| [docs/DEPLOY_HOSTINGER.md](docs/DEPLOY_HOSTINGER.md) | **Pemasangan di Hostinger Cloud, langkah demi langkah** |
| [docs/GOOGLE_DRIVE.md](docs/GOOGLE_DRIVE.md) | Menghubungkan Google Drive (OAuth) |
| [docs/RESTORE.md](docs/RESTORE.md) | Memulihkan dari backup |
| [docs/TINJAUAN_KEAMANAN.md](docs/TINJAUAN_KEAMANAN.md) | Laporan tinjauan keamanan |
| [docs/TAHAP2.md](docs/TAHAP2.md) | NCR & CAPA, Keluhan & Feedback, Risiko & Peluang |

## Menjalankan di komputer lokal (± 10 menit)

Butuh: PHP 8.3 (ekstensi pdo_mysql, zip, fileinfo, mbstring, gd), Composer, MySQL/MariaDB.
Node.js **tidak** wajib — aset tampilan sudah tersedia di `public/build`.

```bash
git clone https://github.com/managerartha/SMM.git smm && cd smm
composer install
cp .env.example .env
php artisan key:generate
```

Buat database `smm` (utf8mb4) lalu isi `DB_USERNAME` / `DB_PASSWORD` di `.env`, serta
`SMM_BACKUP_PASSWORD` (bebas, minimal 12 karakter). Kemudian:

```bash
php artisan migrate --seed
php artisan serve
```

Buka http://localhost:8000. Akun contoh (kata sandi semuanya `password`):

| Email | Peran |
|---|---|
| gm@contoh.test | General Manager (admin) |
| direktur@contoh.test | Direktur |
| mr@contoh.test | Wakil Manajemen Mutu |
| kadiv.ops@contoh.test | Kepala Divisi Operasional |
| cs@contoh.test | Staf CS |
| trainer@contoh.test | Trainer |
| auditor@contoh.test | Auditor (akun berlaku 3 bulan) |

Data contoh Tahap 2: 3 keluhan/feedback/saran, NCR yang sedang dipantau efektivitasnya (dari keluhan) dan NCR baru,
serta 3 risiko/peluang.

Data contoh Tahap 1: 3 dokumen (SOP dengan Rev. 00 kedaluwarsa & Rev. 01 berlaku, IK berlaku, Formulir menunggu review MR)
dan 2 dokumen legalitas (SKP PJK3 berakhir 60 hari lagi, Akta tanpa masa berlaku).

Di lokal, `SMM_DRIVE_DRIVER=local`: berkas disimpan di `storage/app/drive-local` dengan struktur folder yang sama
seperti di Google Drive. Antrian (pindah berkas, email) dijalankan dengan:

```bash
php artisan schedule:work
```

Email di lokal ditulis ke `storage/logs/laravel.log` (`MAIL_MAILER=log`).

## Tes otomatis

Buat database kosong `smm_test` (user yang sama), lalu:

```bash
php artisan test
```

Mencakup: penomoran (termasuk 6 proses paralel tanpa nomor ganda, reset tahunan, nomor tidak dipakai ulang),
alur Draf → Review → Berlaku → Revisi → Kedaluwarsa, matriks pengesahan, hak akses unduhan per peran,
tanda air PDF, legalitas & pengingat, validasi berkas, log append-only, backup terenkripsi & restore.

## Perintah penting

| Perintah | Fungsi |
|---|---|
| `php artisan schedule:run` | Dijalankan cron tiap menit (antrian, pengingat, backup) |
| `php artisan smm:backup harian\|bulanan\|manual` | Backup ke Google Drive |
| `php artisan smm:restore --list` / `--latest` / `--path=berkas.zip` | Pemulihan |
| `php artisan smm:reminders` | Kirim pengingat masa berlaku & tinjauan |
| `php artisan smm:files-push --failed` | Ulangi pemindahan berkas yang gagal ke Drive |
| `php artisan smm:drive-health` | Cek koneksi Drive |
| `php artisan smm:verify-log` | Periksa keutuhan log aktivitas |
| `tools/build-hostinger.sh` | Membuat `dist/smm-hostinger.zip` (diekstrak langsung ke `public_html`) |

## Struktur kode

```
app/Domain/        logika bisnis per modul (tidak bergantung pada HTTP)
  Numbering/       penomoran otomatis aman-paralel
  Documents/       alur dokumen terkendali & unduhan bertanda air
  ExternalDocuments/ legalitas & kontrak
  Files/ Drive/    validasi berkas, penyimpanan Google Drive
  Activity/        log append-only + rantai hash
  Approval/        matriks pengesahan
  Backup/          backup & restore
  Notifications/   email (+ titik sambung WhatsApp), pengingat
  Complaints/ Capa/ Risks/  Tahap 2
  Lms/             (Tahap 3) adapter LMS
app/Policies/      semua aturan hak akses
config/smm.php     peran, izin, master data bawaan, pola nomor
```

Tahap 3–4 (catatan mutu, personel, sarana, survei, KPI, audit, Paket Bukti) memakai ulang tabel `files`,
`number_sequences`, `approval_rules`, `evidence_categories`, dan `activity_logs` tanpa membongkar yang ada.
