# Menghubungkan Google Drive

SMM memakai Google Drive akun **info.arthasafetyindonesia@gmail.com** sebagai penyimpanan utama berkas dan
tujuan backup. Karena akunnya Gmail biasa (bukan Google Workspace), aksesnya memakai **OAuth** dengan izin
`drive.file`: aplikasi **hanya bisa membaca/menulis berkas dan folder yang dibuatnya sendiri**
(folder `SMM` dan `Backup SMM`). Isi Drive lain di akun itu tidak bisa disentuh aplikasi.

## A. Buat kredensial (sekali, ± 15 menit)

Masuk ke **https://console.cloud.google.com** dengan akun `info.arthasafetyindonesia@gmail.com`.

1. **Buat project**: menu project di atas → **New Project** → nama `SMM Artha Safety` → Create.
   > **Jangan pernah menghapus project ini.** Izin `drive.file` terikat pada project; bila project dihapus,
   > aplikasi tidak bisa lagi mengakses berkas lama (berkas tetap ada di Drive dan bisa dibuka manual).
2. **Aktifkan API**: **APIs & Services → Library** → cari **Google Drive API** → **Enable**.
3. **Layar persetujuan** (Google Auth Platform / OAuth consent screen):
   - **Branding**: App name `SMM Artha Safety`, user support email `info.arthasafetyindonesia@gmail.com`.
   - **Audience**: User type **External**.
   - **Data access** → Add scopes → centang `.../auth/drive.file`, `openid`, `.../auth/userinfo.email`.
   - **Audience → Publishing status → Publish app** (ubah dari *Testing* ke **In production**).
     Ini penting: pada status *Testing* token dicabut Google setiap 7 hari dan backup akan berhenti.
     Scope `drive.file` termasuk *non-sensitive*, jadi tidak perlu verifikasi Google.
4. **Clients → Create client** → Application type **Web application** → nama `SMM` →
   **Authorized redirect URIs**: `https://mutu.arthasafetyindonesia.com/admin/google-drive/callback` → Create.
5. Salin **Client ID** dan **Client secret** ke `public_html/.env` (File Manager → klik kanan `.env` → Edit):
   ```
   GOOGLE_DRIVE_CLIENT_ID=xxxxxxxx.apps.googleusercontent.com
   GOOGLE_DRIVE_CLIENT_SECRET=GOCSPX-xxxxxxxx
   ```
   Jangan kirim secret lewat chat/email.

## B. Hubungkan dari SMM

1. Masuk sebagai GM → **Admin → Google Drive** → **Hubungkan Google Drive** (diminta kata sandi SMM).
2. Pilih akun **info.arthasafetyindonesia@gmail.com**. Karena aplikasi belum diverifikasi Google,
   akan muncul peringatan → **Advanced → Go to SMM Artha Safety (unsafe)** → centang izin → **Continue**.
   (Aman: aplikasinya milik Anda sendiri.)
3. Kembali di SMM → **Uji koneksi & siapkan folder**. Di Drive akan muncul folder `SMM` dan `Backup SMM`.

Token disimpan terenkripsi di `storage/app/google/token.json` (tidak bisa dibuka dari web, tidak masuk Git,
dienkripsi dengan `APP_KEY`). Bila `APP_KEY` berubah atau server dipindah, cukup **Hubungkan ulang**.

## Struktur folder di Drive

```
SMM/
├── Dokumen Terkendali/SOP/ASI-SOP-OPS-01/Rev 00 - Penyelenggaraan Pelatihan K3.pdf
│                                        /Rev 01 - Penyelenggaraan Pelatihan K3.pdf
└── Legalitas/SKP/DE-SKP-001/Versi 1 - 5-1234-AS.01.02-XI-2024.pdf
Backup SMM/
├── Harian/smm-harian-2027-01-15-010001.zip   (30 terakhir)
├── Bulanan/smm-bulanan-2027-01-01-020001.zip (12 terakhir)
└── Manual/
```

- Berkas tidak pernah ditimpa atau dihapus aplikasi (setiap revisi/versi = berkas baru).
- **Jangan memindah, mengganti nama, atau menghapus** berkas di folder `SMM` secara manual.
  Backup bulanan memeriksa semua berkas dan mengirim email ke GM bila ada yang hilang.
- Folder bisa dibaca langsung di Drive tanpa aplikasi.

## Salinan kedua (sangat disarankan)

Data utama dan backup berada di satu akun Google. Untuk perlindungan bila akun bermasalah:
1. Aktifkan **verifikasi 2 langkah** di akun Gmail tersebut.
2. Pasang **Google Drive for Desktop** di satu PC kantor, login dengan akun itu, dan pilih **Mirror files**
   untuk folder `SMM` dan `Backup SMM`. PC itu otomatis menyimpan salinan lengkap.

## Bila terputus

Email "Penyimpanan Google Drive bermasalah" dikirim ke GM bila token dicabut / Drive gagal. Unggahan tetap
diterima dan disimpan sementara di server, lalu dipindahkan otomatis setelah koneksi pulih
(Admin → Google Drive → Hubungkan ulang).
