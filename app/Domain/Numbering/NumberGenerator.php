<?php

namespace App\Domain\Numbering;

use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pembuat nomor otomatis.
 *
 * Pola disimpan di pengaturan, mis. "ASI-{jenis}-{divisi}-{urut:2}" atau "NCR/{tahun}/{urut:3}".
 * Cakupan penghitung = jenis objek + semua nilai placeholder selain {urut}. Karena {tahun} ikut cakupan,
 * pola yang memakai {tahun} otomatis mulai dari 1 setiap 1 Januari.
 *
 * Keamanan nomor ganda:
 * 1. Baris penghitung dikunci SELECT ... FOR UPDATE di dalam transaksi.
 * 2. Setiap nomor dicatat di issued_numbers (unik per jenis objek). Nomor yang pernah keluar
 *    tidak akan dipakai lagi walaupun pola diubah atau data diarsip.
 *
 * Panggil di dalam transaksi yang sama dengan penyimpanan data supaya nomor ikut batal jika penyimpanan gagal.
 */
class NumberGenerator
{
    public function next(string $objectType, array $values = [], ?int $userId = null, ?string $pattern = null): string
    {
        $pattern ??= Settings::get('numbering.'.$objectType);
        if (! $pattern || ! preg_match('/\{urut(?::(\d+))?\}/', $pattern)) {
            throw new InvalidArgumentException("Pola nomor untuk {$objectType} tidak valid.");
        }

        $values = array_merge([
            'tahun' => now()->format('Y'),
            'bulan' => now()->format('m'),
        ], $values);

        // Cakupan: placeholder yang benar-benar dipakai pola (selain urut).
        preg_match_all('/\{([a-z_]+)(?::\d+)?\}/', $pattern, $m);
        $scopeParts = [$objectType];
        foreach (array_unique($m[1]) as $name) {
            if ($name === 'urut') {
                continue;
            }
            if (! array_key_exists($name, $values)) {
                throw new InvalidArgumentException("Nilai {{$name}} untuk nomor {$objectType} belum diisi.");
            }
            $scopeParts[] = $name.'='.$values[$name];
        }
        $scopeKey = implode('|', $scopeParts);

        SequenceRows::ensure($scopeKey);

        return DB::transaction(function () use ($objectType, $pattern, $values, $scopeKey, $userId) {
            $seq = DB::table('number_sequences')->where('scope_key', $scopeKey)->lockForUpdate()->first();
            $value = (int) $seq->last_value;

            // Lewati nomor yang sudah pernah dikeluarkan (mis. setelah pola diubah).
            do {
                $value++;
                $number = $this->format($pattern, $values, $value);
                $exists = DB::table('issued_numbers')->where('object_type', $objectType)->where('number', $number)->exists();
            } while ($exists);

            DB::table('number_sequences')->where('id', $seq->id)->update(['last_value' => $value, 'updated_at' => now()]);
            DB::table('issued_numbers')->insert([
                'object_type' => $objectType, 'number' => $number, 'scope_key' => $scopeKey,
                'sequence_value' => $value, 'issued_by' => $userId, 'created_at' => now(),
            ]);

            return $number;
        }, 5);
    }

    public function format(string $pattern, array $values, int $sequence): string
    {
        return preg_replace_callback('/\{([a-z_]+)(?::(\d+))?\}/', function ($m) use ($values, $sequence) {
            if ($m[1] === 'urut') {
                return str_pad((string) $sequence, (int) ($m[2] ?? 1), '0', STR_PAD_LEFT);
            }

            return (string) $values[$m[1]];
        }, $pattern);
    }
}
