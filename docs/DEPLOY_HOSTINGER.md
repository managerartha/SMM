# Memasang SMM di Hostinger Cloud

Panduan ini memakai cara yang sama dengan LMS: **upload ZIP lewat File Manager**, lalu **installer web**.
Tidak perlu SSH.

## Yang perlu disiapkan

- [ ] Akses hPanel Hostinger.
- [ ] Berkas **`smm-hostinger.zip`** (dari Claude, atau buat sendiri dengan `tools/build-hostinger.sh`).
- [ ] Akun Gmail `info.arthasafetyindonesia@gmail.com` (untuk Google Drive).
- [ ] Kata sandi backup: buat kalimat acak panjang (≥ 20 karakter), **catat di tempat aman di luar server**
      (mis. pengelola kata sandi / amplop tertutup di brankas). Tanpa kata sandi ini backup tidak bisa dipulihkan.

## Struktur akhir di server

```
/home/u123456789/domains/mutu.arthasafetyindonesia.com/
├── smm/            ← kode aplikasi, .env, storage (TIDAK bisa diakses dari web)
└── public_html/    ← hanya index.php, .htaccess, folder build
```

Semua berkas unggahan berada di Google Drive. Berkas sementara di `smm/storage/app/private`
(di luar `public_html`) dihapus otomatis setelah pindah ke Drive.

---

## Langkah 1 – Buat website untuk subdomain

1. hPanel → **Websites** → **Add website** → pilih **Empty PHP/HTML website**.
2. Nama domain: `mutu.arthasafetyindonesia.com` → **Create**.
3. Hasilnya folder `domains/mutu.arthasafetyindonesia.com/public_html`.

> Jangan memakai menu **Domains → Subdomains** (yang membuat folder `public_html/mutu` di dalam situs utama),
> karena folder `smm/` harus berada **di luar** `public_html`.

## Langkah 2 – PHP 8.3

1. Pada website `mutu…` → **Advanced → PHP Configuration**.
2. Tab **PHP version**: pilih **8.3** → Save.
3. Tab **PHP options**:
   - `upload_max_filesize` → **32M**, `post_max_size` → **32M**
   - `memory_limit` → **256M**, `max_execution_time` → **300**
4. Tab **PHP extensions**: pastikan `zip`, `fileinfo`, `pdo_mysql`, `mbstring`, `gd`, `intl` tercentang.

## Langkah 3 – Database baru (terpisah dari LMS & leads)

1. **Databases → Management** → buat database `smm` dan user `smm` dengan kata sandi kuat.
2. Catat nama **lengkap** (mis. `u123456789_smm`) untuk database dan user.

## Langkah 4 – Upload & ekstrak

1. **File Manager** → buka folder **`domains/mutu.arthasafetyindonesia.com`** (SATU tingkat di atas `public_html`).
2. Di `public_html`, hapus berkas bawaan seperti `default.php` bila ada.
3. Kembali ke `domains/mutu.arthasafetyindonesia.com` → **Upload** → `smm-hostinger.zip`.
4. Klik kanan ZIP → **Extract** → tujuan: folder yang sedang dibuka (`.`).
5. Pastikan sekarang ada folder `smm/` dan `public_html/` berisi `index.php`, `.htaccess`, `build/`.
6. Hapus `smm-hostinger.zip`.

## Langkah 5 – Berkas `.env`

1. Buka `smm/` → klik kanan **`.env.contoh`** → **Rename** menjadi **`.env`**.
2. Klik kanan `.env` → **Edit**, isi:

| Baris | Isi |
|---|---|
| `APP_KEY=` | **Biarkan kosong** – dibuat otomatis saat halaman pertama dibuka |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Dari Langkah 3 (nama lengkap `u…_smm`) |
| `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | Akun email pengirim, mis. `mutu@arthasafetyindonesia.com` (buat di **Emails**). Server SMTP sudah diisi `smtp.hostinger.com` port 465 |
| `GOOGLE_DRIVE_CLIENT_ID`, `GOOGLE_DRIVE_CLIENT_SECRET` | Dari [GOOGLE_DRIVE.md](GOOGLE_DRIVE.md) (boleh diisi belakangan) |
| `SMM_BACKUP_PASSWORD` | Kata sandi backup yang sudah Anda catat |
| `SMM_INSTALL_TOKEN` | Sudah terisi acak. **Salin** nilainya untuk Langkah 7 |

Nilai lain (`APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SMM_DRIVE_DRIVER=google`) sudah benar.

3. Klik kanan `.env` → **Permissions** → `600`.
4. Folder `smm/storage` dan `smm/bootstrap/cache` → Permissions `755` (rekursif).

## Langkah 6 – SSL (HTTPS)

**Security → SSL** → pasang SSL gratis untuk `mutu.arthasafetyindonesia.com`. `.htaccess` otomatis mengalihkan http → https.

## Langkah 7 – Installer

1. Buka **https://mutu.arthasafetyindonesia.com/install**
2. Semua baris harus ✔. Bila ✘:
   - PHP versi → Langkah 2; Koneksi database → periksa `.env`; Folder bisa ditulis → Permissions 755.
3. Isi token (dari `.env`), nama, email, dan kata sandi **akun GM pertama** → **Pasang sekarang**.
4. Setelah berhasil, edit `.env` dan kosongkan `SMM_INSTALL_TOKEN=`. Installer juga otomatis terkunci.

## Langkah 8 – Cron (satu entri saja)

**Advanced → Cron Jobs** → **Custom**:

```
/opt/alt/php83/usr/bin/php /home/u123456789/domains/mutu.arthasafetyindonesia.com/smm/artisan schedule:run >> /home/u123456789/domains/mutu.arthasafetyindonesia.com/smm/storage/logs/cron.log 2>&1
```

Jadwal: setiap menit (`* * * * *`). Ganti `u123456789` dengan nama akun Anda (terlihat di jalur File Manager).
Bila `/opt/alt/php83/usr/bin/php` tidak ada, pakai `/usr/bin/php`. Setelah beberapa menit, buka
`smm/storage/logs/cron.log` — harus berisi baris "Running … queue:work", bukan pesan galat.

Cron ini menjalankan: antrian email & pemindahan berkas ke Drive (tiap menit), pengingat (07.00 WIB),
backup harian (01.00 WIB), backup bulanan (tgl 1, 02.00 WIB), cek Drive (06.30 WIB), pembersihan berkas sementara.

## Langkah 9 – Google Drive & uji coba

1. Ikuti [GOOGLE_DRIVE.md](GOOGLE_DRIVE.md) → Admin → Google Drive → **Hubungkan** → **Uji koneksi**.
2. Admin → Backup → **Jalankan backup sekarang** → cek folder `Backup SMM/Manual` di Drive.
3. Uji email: keluar → **Lupa kata sandi** → email Anda → pastikan email diterima.
4. Admin → Pengguna → tambahkan Direktur, MR, kepala divisi, staf, trainer.
5. Ganti matriks pengesahan bila perlu (Admin → Matriks Pengesahan).

## Daftar periksa keamanan setelah pemasangan

- [ ] `https://mutu.arthasafetyindonesia.com/.env` → 403/404
- [ ] `APP_DEBUG=false`, `SMM_INSTALL_TOKEN` kosong
- [ ] Aktifkan verifikasi dua langkah (Profil) untuk GM, Direktur, MR
- [ ] Aktifkan verifikasi 2 langkah di akun Gmail `info.arthasafetyindonesia@…`
- [ ] Kata sandi backup tercatat di luar server
- [ ] (Disarankan) Google Drive for Desktop di PC kantor menyinkronkan folder `SMM` dan `Backup SMM` (salinan kedua)

---

## Memperbarui ke versi baru

1. Unggah ZIP versi baru ke `domains/mutu.arthasafetyindonesia.com` → **Extract** → setujui **Replace/Overwrite**.
   ZIP tidak berisi `.env` maupun isi `storage/`, jadi konfigurasi dan data aman.
2. Masuk sebagai GM → **Admin → Pembaruan Sistem** → **Jalankan pembaruan** (migrasi database + cache).

## Masalah umum

| Gejala | Solusi |
|---|---|
| 403 Forbidden di halaman utama | Pastikan `public_html/index.php` & `public_html/.htaccess` ada langsung di `public_html` (bukan `public_html/public_html/`), permission folder 755 & berkas 644, dan `.htaccess` diawali `DirectoryIndex index.php` |
| Error 500 / halaman kosong | Cek PHP 8.3 (Langkah 2). Lihat `smm/storage/logs/laravel.log` |
| "No application encryption key" | `.env` tidak bisa ditulis saat pertama dibuka → isi `APP_KEY` manual: minta Claude membuatkan, atau ubah permission `.env` ke 644 sementara lalu muat ulang |
| Upload gagal untuk berkas besar | Naikkan `upload_max_filesize`/`post_max_size` (Langkah 2) dan Admin → Pengaturan → ukuran maksimum |
| "PDF ini tidak bisa diproses untuk tanda air" | Simpan ulang dari Word: Save As → PDF → Options → centang **PDF/A compliant** |
| Berkas lama "menunggu pindah ke Drive" | Cron belum jalan (Langkah 8) atau Drive belum terhubung (Admin → Google Drive) |
| Email tidak terkirim | Periksa `MAIL_*` di `.env`; cron harus jalan karena email dikirim lewat antrian |
| 419 "Sesi berakhir" | Muat ulang halaman; sesi otomatis berakhir setelah 30 menit tidak aktif |
