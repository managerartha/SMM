# Tahap 4 – Audit Internal, Tinjauan Manajemen, Program Inovasi, Laporan Kinerja, Paket Bukti

| Modul | Menu | Nomor | Pengesah bawaan (Matriks Pengesahan) | Kategori bukti |
|---|---|---|---|---|
| Program audit tahunan | Kinerja → Audit Internal | per tahun | Direktur | 13 |
| Audit per proses | Kinerja → Audit Internal | `AI/2027/01` | GM (bukan auditornya sendiri) | 13 |
| Tinjauan Manajemen | Kinerja → Tinjauan Manajemen | `TM/2027/01` | Direktur | 16 |
| Program Inovasi Mutu | Kinerja → Program Inovasi | `INV/2027/01` | GM (tanpa anggaran) / Direktur (dengan anggaran) | 12 |
| Laporan Kinerja Layanan | Kinerja → Laporan Kinerja | `LKL/2027/03` (bulanan), `LKL/2027` (tahunan) | GM (bulanan) / Direktur (tahunan) | 9 |
| Paket Bukti | Kinerja → Paket Bukti | – | – (izin `paket.buat`) | semua |

Semua yang disahkan otomatis dibuatkan **PDF resmi** (nama, jabatan, dan waktu pengesah tercetak) yang disimpan di
Google Drive. PDF dibuat di server dengan dompdf (tanpa layanan luar).

## Audit Internal (ISO 9001 klausul 9.2)
1. MR membuat **program audit** tahun berjalan (tujuan, ruang lingkup, kriteria).
2. **Jadwalkan audit** per proses/divisi: pilih auditor dan tanggal (harus di tahun program).
   - Auditor harus memiliki izin `audit.laksana` (MR, Kadiv, Auditor) dan **tidak boleh mengaudit divisinya sendiri**.
   - Checklist bawaan berisi 10 butir ISO 9001; auditor bisa menambah butir.
   - Bila jadwal ditambah setelah program disahkan, program kembali ke draf dan perlu disahkan ulang.
3. Program **diajukan** dan **disahkan Direktur**, lalu PDF Program Audit dibuat.
4. Auditor mengisi checklist (Sesuai / Tidak sesuai / Observasi / Tidak berlaku + bukti objektif) dan mencatat temuan.
   - Temuan **mayor/minor otomatis menjadi NCR** (sumber "Audit internal"). PIC-nya auditi atau kepala divisi, dengan target 30 hari (mayor) atau 60 hari (minor).
   - Observasi dan peluang perbaikan dicatat tanpa NCR.
5. Auditor mengisi kesimpulan dan mengajukan laporan. Laporan disahkan GM, lalu PDF Laporan Audit dibuat.
6. Pengingat email dikirim ke auditor dan auditi 7 hari sebelum audit.

## Tinjauan Manajemen (klausul 9.3)
- Saat dibuat, **semua masukan 9.3.2 terisi otomatis** dari data SMM pada periode yang dipilih. Isinya: status tindakan tinjauan sebelumnya, kepuasan pelanggan, KPI, kinerja kelas, NCR, hasil audit, pemasok, sumber daya, risiko, dan inovasi.
- Masukan bisa disunting, dan tombol **Isi ulang** mengambil angka terbaru.
- Keluaran 9.3.3 terdiri atas peluang perbaikan, perubahan SMM, kebutuhan sumber daya, dan kesimpulan (wajib).
- **Tindak lanjut** punya PIC dan batas waktu. Tindak lanjut muncul di dashboard PIC, dengan pengingat 7 hari sebelum batas dan saat lewat batas. PIC menandai selesai dengan menuliskan hasilnya.
- Notulen yang sudah disahkan Direktur dibuatkan PDF Notulen Tinjauan Manajemen.

## Program Inovasi Mutu
Alurnya: Usulan → Disetujui/Ditolak → Pelaksanaan → Selesai.
- Semua pegawai bisa mengusulkan.
- Pengesah ditentukan otomatis dari anggaran (GM atau Direktur). Penolakan wajib disertai alasan.
- Status Selesai **wajib disertai minimal satu bukti** (foto, dokumen), nilai hasil indikator, dan evaluasi.
- Daftar inovasi bisa diekspor ke Excel.

## Laporan Kinerja Layanan
- **Susun dari data SMM**, bulanan atau tahunan. Isinya: KPI (target vs realisasi), kelas, kepuasan, keluhan, NCR, audit, dan risiko.
- Selama belum disahkan, angka bisa **diperbarui**. Laporan yang sudah disahkan tidak bisa disusun ulang.
- Analisis wajib diisi sebelum diajukan. Setelah disahkan, PDF dibuat.

## Paket Bukti (untuk penilaian PJK3)
Pilih rentang tanggal, lalu klik **Buat paket**. Paket disusun di latar belakang lewat cron, biasanya beberapa menit tergantung jumlah berkas di Drive. Hasilnya satu ZIP berisi:

- `00_Daftar_Isi.pdf`: daftar semua berkas per folder.
- 16 folder sesuai kategori bukti:
  - Dokumen terkendali yang berlaku, diberi tanda air *SALINAN TERKENDALI – Paket Bukti*.
  - Legalitas.
  - Catatan mutu.
  - Berkas personel TA/TT.
  - Sarana: daftar Excel, foto, dan riwayat pemeriksaan.
  - Laporan kinerja dan KPI.
  - Rekap survei (PDF + Excel).
  - Keluhan.
  - Inovasi.
  - Program & laporan audit.
  - NCR: cetak PDF per NCR, bukti, dan register.
  - Efektivitas.
  - Uraian sistem digital, notulen tinjauan, dan log aktivitas.

Detail lainnya:
- ZIP disimpan di Google Drive folder **Paket Bukti**. Unduhannya bisa dilakukan oleh pemegang izin `paket.unduh` (termasuk Auditor).
- Hanya satu paket yang dibuat dalam satu waktu. Berkas yang tidak bisa diambil dicatat sebagai "catatan" di halaman Paket Bukti, dan paketnya tetap dibuat.

## Izin baru
`mutu.sahkan`, `audit.lihat`, `audit.kelola`, `audit.laksana`, `tinjauan.lihat`, `tinjauan.kelola`, `inovasi.lihat`,
`inovasi.usul`, `inovasi.kelola`, `laporan.lihat`, `laporan.kelola`, `paket.buat`, `paket.unduh`.
Izin ini ditambahkan ke peran bawaan saat pembaruan, tanpa menimpa penyesuaian GM. Ubah di **Admin → Peran & Izin**.
