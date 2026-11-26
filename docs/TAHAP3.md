# Tahap 3 – Kelas & Catatan Mutu, Personel, Sarana, Survei, KPI, Pemasok

| Modul | Menu | Nomor | Kategori bukti |
|---|---|---|---|
| Kelas dari LMS | Pelaksanaan → Kelas (LMS) | Kode kelas LMS (tidak dibuat SMM) | – |
| Catatan Mutu | Dokumen → Catatan Mutu | `F-OPS-01/2027/0045` (kode formulir/tahun/urut); tanpa formulir `CM/2027/0001` | 8 (bawaan, bisa diubah) |
| Personel | Pelaksanaan → Personel | `TA-001` (Tenaga Ahli), `TT-001` (Tenaga Teknis) | 3 / 4 |
| Sarana & Prasarana | Pelaksanaan → Sarana & Prasarana | `SP-ALT-012` | 6 |
| Rekap Survei | Pelaksanaan → Rekap Survei | – | 10 |
| Sasaran Mutu & KPI | Kinerja → Sasaran Mutu & KPI | `KPI-01` | 9 |
| Pemasok | Pelaksanaan → Pemasok | `PMS-001` | – |
| Program Pelatihan | Admin → Program Pelatihan | kode bebas | – |

## Alur yang disarankan
1. **Admin → Program Pelatihan**: isi program (AK3U, P3K, …) agar kelas LMS tercocokkan.
2. **Kelas → Impor dari LMS**: unggah ekspor tabel `batch` (phpMyAdmin) atau template. Lihat [LMS_ADAPTER.md](LMS_ADAPTER.md).
3. Setelah kelas selesai: **Catatan mutu kelas ini** → unggah scan daftar hadir, laporan trainer, foto, berita acara.
4. **Survei**: impor respons survei (setelah fitur survei LMS jadi). Respons negatif → **Jadikan keluhan**.
5. **KPI**: buat sasaran mutu tahunan. Sumber otomatis dihitung tiap hari pukul 05.30 (kepuasan, kelulusan, kehadiran,
   keluhan tepat waktu, tindakan korektif tepat waktu, jumlah kelas/keluhan/NCR). KPI manual (mis. respons CS dari
   sistem leads) diisi per bulan. Nilai manual tidak pernah ditimpa perhitungan otomatis.
6. **Personel**: unggah CV, ijazah, sertifikat/lisensi K3, SK, kontrak. Perpanjangan → pilih "Memperbarui berkas lama"
   (berkas lama tetap sebagai riwayat). Pengingat email 90/60/30 hari.
7. **Sarana**: isi interval pemeriksaan & jadwal kalibrasi. Pengingat email 14 hari sebelumnya.
8. **Pemasok**: evaluasi per semester (mutu, ketepatan waktu, harga, layanan → keputusan).

## Hak akses (bawaan, bisa diubah di Admin → Peran & Izin)
- **GM, MR**: kelola semua. **Direktur, Auditor**: lihat semua.
- **Kepala divisi**: lihat semua; catat catatan mutu, kelola sarana, isi realisasi KPI divisinya.
- **Staf / Trainer**: lihat kelas; catat & lihat catatan mutu divisinya; staf juga melihat sarana.

## Memasang pembaruan
Unggah ZIP / berkas → Extract (Replace) → buka SMM: GM otomatis diarahkan ke **Pembaruan Sistem** → **Jalankan pembaruan**.
