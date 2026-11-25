# Memasang SMM di Hostinger (5 langkah)

Caranya sama seperti LMS: **satu ZIP diekstrak ke `public_html`, lalu satu formulir di browser.**
Tidak perlu SSH dan tidak perlu mengedit `.env`.

Siapkan: akses hPanel dan berkas **`smm-hostinger.zip`**.

---

## Langkah 1 – Buat website PHP

1. hPanel → **Websites** → **Add website** → pilih **Empty PHP/HTML website** (website PHP kosong).
2. Domain: `mutu.arthasafetyindonesia.com`.
3. Buka website itu → **Advanced → PHP Configuration** → pilih **PHP 8.3** → Save.

> ⚠️ **Jangan pilih "Node.js app"**. Bila dashboard website menampilkan ikon "JS", "Versi node", atau
> "Root directory", website itu terbuat sebagai Node.js dan **PHP tidak akan jalan (muncul 403)**.
> Hapus website itu (⋮ → Hapus) lalu ulangi Langkah 1. Website PHP yang benar memiliki menu **File Manager**.

## Langkah 2 – Buat database

hPanel → **Databases → Management** → buat database baru + user + kata sandi.
Catat **nama lengkap** database dan user (mis. `u123456789_smm`) serta kata sandinya.

## Langkah 3 – Unggah ZIP

1. **File Manager** → buka folder **`public_html`** milik `mutu.arthasafetyindonesia.com`.
2. Hapus berkas bawaan seperti `default.php` / `index.html` bila ada.
3. **Upload** → `smm-hostinger.zip` → klik kanan → **Extract** → tujuan: folder ini (`.`), bukan folder baru.
4. Isi `public_html` sekarang: `index.php`, `.htaccess`, `.env.contoh`, folder `app`, `public`, `storage`, `vendor`, dll.
5. Hapus `smm-hostinger.zip`.

> Semua permintaan dari browser dialihkan `.htaccess` ke aplikasi, jadi `.env`, `storage`, `vendor`, dll.
> tidak bisa dibuka dari luar walaupun berada di `public_html`.

## Langkah 4 – Installer

Buka **https://mutu.arthasafetyindonesia.com/install**, pastikan semua baris ✔, lalu isi:

1. **Database** – dari Langkah 2.
2. **Email pengirim** (boleh nanti) – akun email Hostinger, mis. `mutu@arthasafetyindonesia.com` (buat di hPanel → Emails).
3. **Kata sandi backup** – sudah terisi acak. **Salin dan simpan di tempat aman di luar server.**
4. **Akun General Manager** – nama, email, kata sandi Anda.

Klik **Pasang sekarang**. Halaman selesai menampilkan kata sandi backup (sekali lagi) dan **perintah cron**.
Setelah itu `/install` terkunci.

## Langkah 5 – Cron

hPanel → **Advanced → Cron Jobs** → **Custom** → tempel perintah dari halaman selesai, jadwal **setiap menit**:

```
/usr/bin/php /home/u123456789/domains/mutu.arthasafetyindonesia.com/public_html/artisan schedule:run
```

(Bila hPanel meminta jenis "PHP", pilih PHP lalu isi hanya jalur `…/public_html/artisan schedule:run`.)
Cron menjalankan antrian email & pemindahan berkas ke Google Drive, pengingat, dan backup.

---

## Setelah terpasang

1. Masuk sebagai GM → **Admin → Google Drive** → ikuti [GOOGLE_DRIVE.md](GOOGLE_DRIVE.md) → **Hubungkan** → **Uji koneksi**.
   (Client ID & secret Google diisi di `public_html/.env` lewat File Manager → klik kanan `.env` → Edit. Hanya ini yang perlu diedit manual.)
2. **Admin → Backup → Jalankan backup sekarang** → cek folder `Backup SMM/Manual` di Drive.
3. Profil → aktifkan **verifikasi dua langkah**. Tambahkan pengguna lain di Admin → Pengguna.
4. Uji email: keluar → **Lupa kata sandi** → pastikan email diterima.

## Memperbarui ke versi baru

Unggah ZIP baru ke `public_html` → **Extract** → setujui **Replace**. ZIP tidak berisi `.env` dan data di
`storage/`, jadi aman. Lalu masuk sebagai GM → **Admin → Pembaruan Sistem → Jalankan pembaruan**.

## Masalah umum

| Gejala | Solusi |
|---|---|
| **403 Forbidden** | Website terbuat sebagai Node.js (lihat Langkah 1), atau `index.php` & `.htaccess` tidak langsung berada di `public_html` (mis. terekstrak ke `public_html/smm-hostinger/` → pindahkan isinya satu tingkat ke atas) |
| Baris ✘ di installer | PHP versi → Langkah 1 no. 3. "bisa ditulis" → File Manager → klik kanan folder `storage`, `bootstrap/cache` → Permissions 755 |
| Error 500 / halaman kosong | Pastikan PHP 8.3. Lihat `public_html/storage/logs/laravel.log` |
| "Tidak bisa terhubung ke database" | Pakai nama **lengkap** `u…_smm` untuk database & user; cek kata sandi di hPanel → Databases |
| Upload gagal untuk berkas besar | PHP Configuration → PHP options: `upload_max_filesize` & `post_max_size` = 32M |
| "PDF ini tidak bisa diproses untuk tanda air" | Simpan ulang dari Word: Save As → PDF → Options → centang **PDF/A compliant** |
| Berkas "menunggu pindah ke Drive" / email tidak terkirim | Cron belum jalan (Langkah 5) atau Drive belum terhubung |
| 419 "Sesi berakhir" | Muat ulang halaman (sesi berakhir setelah 30 menit tidak aktif) |
