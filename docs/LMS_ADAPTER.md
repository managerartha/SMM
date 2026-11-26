# Adapter LMS → SMM

LMS adalah **sumber utama data kelas**. SMM hanya **membaca** dan menyimpan **rekap per kelas** serta respons survei
**tanpa data pribadi peserta** (nama, HP, email, NIK, alamat diabaikan bila ada di berkas). SMM tidak pernah menulis ke LMS.

Kode modul lain tidak bergantung pada cara pengambilan data: semuanya lewat antarmuka `App\Domain\Lms\LmsSource`
dengan dua implementasi:

| Sumber | Kelas | Status |
|---|---|---|
| Impor CSV / Excel manual | `CsvLmsSource` | **Aktif** (menu Kelas → Impor dari LMS) |
| API baca-saja LMS | `ApiLmsSource` | Siap; aktif bila `SMM_LMS_API_URL` & `SMM_LMS_API_TOKEN` diisi di `.env` (sinkron harian 05.00) |

## 1. Impor rekap kelas

Kode kelas LMS (`kode_batch`) dipakai apa adanya. Kode yang sudah ada **diperbarui**, tidak diduplikasi.

| Kolom SMM | Judul kolom yang dikenali |
|---|---|
| Kode kelas (wajib) | `kode_kelas`, `kode_batch`, `kode` |
| Program (wajib untuk kelas baru) | `program`, `nama_pelatihan`, `nama_program` |
| Kode program | `kode_program`, `id_program` (dicocokkan ke Admin → Program Pelatihan) |
| Skema | `skema`, `jenis_pelatihan` |
| Klien inhouse | `klien`, `perusahaan`, `perusahaan_inhouse` |
| Lokasi | `lokasi`, `lokasi_nama` |
| Tanggal mulai / selesai | `tgl_mulai`, `tgl_selesai` (format 2027-01-31, 31/01/2027, atau tanggal Excel) |
| Trainer | `trainer`, `pemateri`, `instruktur` |
| Jumlah peserta / hadir penuh / lulus | `jumlah_peserta`, `hadir`, `lulus` |
| Rata-rata nilai | `rata_nilai`, `nilai_rata_rata` |
| Status | `status` |

**Cara cepat dari LMS sekarang:** phpMyAdmin → database LMS → tabel `batch` → **Export** → format **CSV** → unggah di SMM.
Untuk jumlah peserta/hadir/lulus, isi kolom tambahan di template (`kelas.xlsx`) atau tunggu API.

## 2. Impor respons survei

| Kolom | Judul kolom |
|---|---|
| ID respons (disarankan; mencegah impor ganda) | `id_respons`, `id` |
| Kode kelas | `kode_kelas`, `kode_batch` |
| Jenis survei (wajib) | `jenis_survei`: `kepuasan_peserta` / `evaluasi_trainer` / `evaluasi_klien` (juga `kepuasan`, `trainer`, `klien`) |
| Tanggal | `tanggal` |
| Skor keseluruhan 1–5 | `skor` (kosong = rata-rata kolom aspek) |
| Skor per aspek 1–5 | kolom berawalan `aspek_`, mis. `aspek_materi`, `aspek_trainer`, `aspek_fasilitas` |
| Yang dinilai | `trainer`, `subjek` |
| Komentar | `komentar`, `saran` |

Respons dengan skor ≤ 2,5 (Admin → Pengaturan) ditandai **negatif** dan bisa dijadikan **keluhan** dengan satu klik.

## 3. Kontrak API baca-saja (untuk dikembangkan di LMS)

```
GET {SMM_LMS_API_URL}/kelas?sejak=YYYY-MM-DD
GET {SMM_LMS_API_URL}/survei?sejak=YYYY-MM-DD
Authorization: Bearer {SMM_LMS_API_TOKEN}

200 OK
{"data": [ { ...kunci sama dengan judul kolom di atas... } ]}
```

Contoh `/kelas`: `{"data":[{"kode_batch":"AK3U-2027-01","nama_pelatihan":"Ahli K3 Umum","tgl_mulai":"2027-01-06","tgl_selesai":"2027-01-17","jumlah_peserta":25,"hadir":24,"lulus":23,"rata_nilai":81.5,"trainer":"Hendra"}]}`

Di LMS, endpoint ini cukup menghitung dari tabel `batch`, `pengguna` (role peserta), `absensi`, dan `nilai` lalu mengembalikan
**angka rekap saja**. Token disimpan di `.env` kedua aplikasi; jangan dikirim lewat URL.

## 4. Status: API LMS sudah aktif (Nov 2026)

LMS Artha Safety sudah menyediakan ketiga endpoint (lihat `docs/API-SMM.md` di repo LMS):

| Endpoint | Masuk ke SMM |
|---|---|
| `GET /api/smm/kelas` | Pelaksanaan → Kelas (LMS). `trainer` berupa daftar → digabung dengan koma |
| `GET /api/smm/survei` | Rekap Survei (sekali per `id_respons`) |
| `GET /api/smm/keluhan` | Keluhan & Feedback: jenis *keluhan*, saluran *LMS*, sekali per nomor tiket (`id_keluhan`) |

**Keluhan dari LMS** otomatis:
- dinomori seperti keluhan lain;
- diberi batas tanggapan (pengaturan `complaint.response_days`, bawaan 3 hari);
- dinotifikasikan ke pemegang izin `keluhan.kelola`;
- tercatat "dicatat oleh Sistem (dari LMS)".

Kategori LMS dipetakan ke SMM: fasilitas → Sarana, dokumen → Sertifikat, sedangkan materi, trainer, pelayanan, jadwal, dan lainnya tetap sama.
**Kontak pelapor tidak disalin.** Bila perlu menghubungi pelapor, tanyakan nomor tiketnya ke Admin LMS.

**Jadwal tarik:**
- tiap jam, data 14 hari terakhir;
- tiap hari pukul 05.00, data 120 hari terakhir (agar angka lulus/nilai yang diisi belakangan ikut diperbarui).

Tombol **Sinkron sekarang** ada di Kelas → Impor dari LMS.

**Catatan `hadir`:** LMS mengirim jumlah peserta yang hadir **minimal 1 sesi**. Bila LMS kelak menambahkan kolom `hadir_penuh`, SMM otomatis memakainya untuk KPI kehadiran penuh.

**Pengaturan di `.env` SMM:**
```
SMM_LMS_API_URL=https://lms.arthasafetyindonesia.com/api/smm
SMM_LMS_API_TOKEN=<sama persis dengan SMM_API_TOKEN di public_html/app/.env LMS>
```
