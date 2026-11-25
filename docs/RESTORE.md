# Memulihkan SMM dari Backup

Isi setiap backup (`smm-harian-…zip` / `smm-bulanan-…zip` di Drive folder `Backup SMM`):

| Berkas di ZIP | Isi |
|---|---|
| `database.sql` | Seluruh database (dokumen, revisi, pengguna, log, pengaturan) |
| `manifest.json` | Daftar semua berkas: ID Drive, jalur folder, checksum SHA-256 |
| `files/…` | Berkas yang saat backup belum sempat pindah ke Drive (biasanya kosong) |
| `BACA-SAYA.txt` | Keterangan (tidak terenkripsi) |

ZIP terenkripsi **AES-256** dengan `SMM_BACKUP_PASSWORD`. Berkas dokumen sendiri tidak ada di ZIP karena
sudah tersimpan permanen di folder `SMM` di Drive dan dirujuk lewat ID di database.

> ⚠️ Restore **menimpa seluruh database** dengan isi backup. Data yang dibuat setelah backup akan hilang.
> Lakukan di luar jam kerja dan beri tahu pengguna.

## Skenario A – Server masih ada, database rusak/terhapus

Tanpa SSH, jalankan lewat **cron sekali jalan**:

1. hPanel → **Advanced → Cron Jobs** → Custom, jadwal 1–2 menit ke depan, perintah:
   ```
   /opt/alt/php83/usr/bin/php /home/u123456789/domains/mutu.arthasafetyindonesia.com/public_html/artisan smm:restore --latest --force >> /home/u123456789/domains/mutu.arthasafetyindonesia.com/public_html/storage/logs/restore.log 2>&1
   ```
   Untuk backup tertentu: ganti `--latest` dengan `--id=ID_BERKAS` (lihat daftar: perintah `smm:restore --list`
   dengan cara yang sama, hasilnya di log).
2. Setelah selesai (cek `storage/logs/restore.log` berisi "Pemulihan selesai"), **hapus cron sekali jalan itu**.
3. Login dan periksa beberapa dokumen terakhir.

Dengan SSH (bila diaktifkan di hPanel → Advanced → SSH Access):

```bash
cd ~/domains/mutu.arthasafetyindonesia.com/public_html
php artisan smm:restore --list
php artisan smm:restore --latest
```

## Skenario B – Pindah / pasang ulang server

1. Pasang SMM sesuai [DEPLOY_HOSTINGER.md](DEPLOY_HOSTINGER.md) Langkah 1–4 dan 5. Pada installer gunakan
   **database baru yang kosong** dan kata sandi backup yang **sama** dengan backup yang akan dipulihkan. Isi kredensial
   Google dari **project Google Cloud yang sama** di `.env`. (Akun GM dari installer akan tertimpa oleh isi backup.)
2. Unduh ZIP backup terbaru dari Drive (`Backup SMM/Harian`) ke komputer, lalu unggah lewat File Manager ke
   `public_html/storage/app/private/`.
3. Jalankan lewat cron sekali jalan (lihat Skenario A) atau SSH:
   ```
   /opt/alt/php83/usr/bin/php /home/u123456789/domains/mutu.arthasafetyindonesia.com/public_html/artisan smm:restore --path=/home/u123456789/domains/mutu.arthasafetyindonesia.com/public_html/storage/app/private/NAMA-BACKUP.zip --force
   ```
   Restore membuat semua tabel sendiri (sudah diuji pada database kosong).
4. Hapus ZIP dari `storage/app/private/` dan hapus cron sekali jalan.
5. Login dengan akun & kata sandi sesuai isi backup → Admin → Google Drive → **Hubungkan ulang** dengan akun
   `info.arthasafetyindonesia@gmail.com`. Semua berkas lama langsung bisa diunduh lagi
   (token Google lama tidak ikut dipulihkan karena terikat `APP_KEY` server lama).

## Skenario C – Membaca backup tanpa aplikasi

1. Unduh ZIP dari Drive → buka dengan **7-Zip** (Windows) atau **Keka** (Mac) → masukkan kata sandi backup.
2. `database.sql` bisa diimpor lewat **phpMyAdmin** (hPanel → Databases → phpMyAdmin → Import).
3. Dokumen sendiri bisa dibuka langsung di Drive, folder `SMM/…` (nama folder = nomor dokumen).
   `manifest.json` memetakan setiap berkas ke jalur dan checksum-nya.

## Uji pemulihan berkala

Disarankan setiap 6 bulan: pasang salinan SMM di subdomain uji (mis. `uji-mutu…`) dengan database terpisah,
pulihkan backup terbaru (Skenario B), dan pastikan dokumen bisa dibuka. Catat hasilnya sebagai bukti
pengendalian rekaman (ISO 9001 klausul 7.5.3).

Uji otomatis untuk backup → restore ada di `tests/Feature/BackupRestoreTest.php` (memakai penyimpanan lokal
yang meniru struktur Drive).
