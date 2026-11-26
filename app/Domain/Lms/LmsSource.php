<?php

namespace App\Domain\Lms;

/**
 * Sumber data LMS (baca-saja). Modul lain hanya bergantung pada antarmuka ini:
 * - CsvLmsSource : impor CSV/Excel manual (cadangan, dipakai sekarang)
 * - ApiLmsSource : API baca-saja LMS (diaktifkan bila LMS sudah menyediakan endpoint)
 * SMM tidak pernah menulis ke LMS.
 */
interface LmsSource
{
    public function label(): string;

    /** @return iterable<int, array{row:int, data:array}> baris kelas (kunci sudah dinormalisasi) */
    public function classes(): iterable;

    /** @return iterable<int, array{row:int, data:array}> baris respons survei */
    public function surveys(): iterable;
}
