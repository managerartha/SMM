<?php

namespace App\Domain\Lms;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;

/** Penamaan kolom yang diterima (template SMM, ekspor tabel `batch` LMS lewat phpMyAdmin, atau JSON API). */
class FieldMap
{
    public const CLASS_FIELDS = [
        'class_code' => ['kode_kelas', 'kode_batch', 'kode', 'class_code'],
        'program_name' => ['program', 'nama_pelatihan', 'nama_program', 'pelatihan'],
        'program_code' => ['kode_program', 'id_program'],
        'scheme' => ['skema', 'jenis_pelatihan', 'jenis'],
        'client' => ['klien', 'perusahaan', 'perusahaan_inhouse', 'instansi'],
        'location' => ['lokasi', 'lokasi_nama', 'tempat'],
        'start_date' => ['tgl_mulai', 'tanggal_mulai', 'mulai'],
        'end_date' => ['tgl_selesai', 'tanggal_selesai', 'selesai'],
        'trainers' => ['trainer', 'pemateri', 'instruktur', 'narasumber'],
        'participants' => ['jumlah_peserta', 'peserta'],
        'attended' => ['hadir_penuh', 'hadir', 'jumlah_hadir'],
        'passed' => ['lulus', 'jumlah_lulus'],
        'average_score' => ['rata_nilai', 'nilai_rata_rata', 'rata_rata', 'rata_rata_nilai'],
        'lms_status' => ['status', 'status_kelas'],
    ];

    public const SURVEY_FIELDS = [
        'class_code' => ['kode_kelas', 'kode_batch', 'kode', 'class_code'],
        'survey_type' => ['jenis_survei', 'survei', 'jenis'],
        'responded_at' => ['tanggal', 'tgl', 'tanggal_isi', 'waktu'],
        'score' => ['skor', 'nilai', 'skor_keseluruhan', 'score'],
        'comment' => ['komentar', 'saran', 'masukan', 'comment'],
        'subject' => ['subjek', 'trainer', 'yang_dinilai'],
        'external_id' => ['id_respons', 'id', 'external_id'],
    ];

    public const COMPLAINT_FIELDS = [
        'external_id' => ['id_keluhan', 'nomor_tiket', 'id', 'external_id'],
        'class_code' => ['kode_kelas', 'kode_batch', 'class_code'],
        'received_at' => ['tanggal', 'tgl', 'waktu'],
        'category' => ['kategori', 'category'],
        'description' => ['uraian', 'isi', 'keluhan', 'deskripsi'],
    ];

    /** Kolom data pribadi yang sengaja DIABAIKAN (tidak pernah disimpan SMM). */
    public const IGNORED_PERSONAL = ['nama', 'nama_peserta', 'email', 'no_hp', 'hp', 'telepon', 'nik', 'alamat', 'tgl_lahir', 'tempat_lahir'];

    public static function key(string $header): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', Str::lower(Str::ascii(trim($header)))), '_');
    }

    /** Normalisasi satu baris mentah (kunci header bebas) menjadi kunci SMM. */
    public static function normalize(array $raw, array $fields): array
    {
        $byKey = [];
        foreach ($raw as $k => $v) {
            if (is_array($v)) { // mis. daftar trainer dari API LMS
                $v = implode(', ', array_filter(array_map(fn ($x) => is_scalar($x) ? trim((string) $x) : '', $v), fn ($x) => $x !== ''));
            }
            $byKey[self::key((string) $k)] = $v;
        }
        $out = [];
        foreach ($fields as $target => $aliases) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $byKey) && $byKey[$alias] !== '' && $byKey[$alias] !== null) {
                    $out[$target] = $byKey[$alias];
                    break;
                }
            }
        }
        // Skor per aspek survei: kolom berawalan aspek_
        $aspects = [];
        foreach ($byKey as $k => $v) {
            if (str_starts_with($k, 'aspek_') && is_numeric($v)) {
                $aspects[substr($k, 6)] = (float) $v;
            }
        }
        if ($aspects) {
            $out['aspects'] = $aspects;
        }

        return $out;
    }

    public static function date(mixed $v): ?string
    {
        if ($v instanceof DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        $v = trim((string) $v);
        if ($v === '' || str_starts_with($v, '0000')) {
            return null;
        }
        if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) { // nomor seri tanggal Excel
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $v)->format('Y-m-d');
        }
        foreach (['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y', 'd/m/y', 'Y/m/d', 'd.m.Y'] as $fmt) {
            try {
                $d = CarbonImmutable::createFromFormat('!'.$fmt, $v);
                if ($d && $d->format($fmt) === $v) {
                    return $d->format('Y-m-d');
                }
            } catch (\Throwable) {
            }
        }
        try {
            return CarbonImmutable::parse($v)->format('Y-m-d');
        } catch (\Throwable) {
            throw new \InvalidArgumentException("Format tanggal tidak dikenali: {$v}");
        }
    }

    public static function number(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }
        $v = str_replace([' ', '%'], '', (string) $v);
        $v = str_contains($v, ',') && ! str_contains($v, '.') ? str_replace(',', '.', $v) : str_replace(',', '', $v);
        if (! is_numeric($v)) {
            throw new \InvalidArgumentException("Bukan angka: {$v}");
        }

        return (float) $v;
    }
}
