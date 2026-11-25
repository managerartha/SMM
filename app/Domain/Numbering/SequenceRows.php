<?php

namespace App\Domain\Numbering;

use Illuminate\Support\Facades\DB;

/**
 * Memastikan baris penghitung ada SEBELUM dikunci, lewat koneksi terpisah (autocommit).
 * Membuat baris di dalam transaksi yang sama dengan SELECT ... FOR UPDATE memicu
 * deadlock (gap lock) saat banyak orang menyimpan bersamaan — ini terbukti di tes paralel.
 */
class SequenceRows
{
    public static function ensure(string $scopeKey): void
    {
        if (DB::table('number_sequences')->where('scope_key', $scopeKey)->exists()) {
            return;
        }
        $name = config('database.default');
        $aux = $name.'_aux';
        if (! config("database.connections.{$aux}")) {
            config(["database.connections.{$aux}" => config("database.connections.{$name}")]);
        }
        DB::connection($aux)->table('number_sequences')->insertOrIgnore([
            'scope_key' => $scopeKey, 'last_value' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
