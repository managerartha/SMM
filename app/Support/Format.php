<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class Format
{
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** Format tanggal d MMM yyyy, mis. 05 Des 2026. */
    public static function date(CarbonInterface|string|null $date): string
    {
        if (! $date) {
            return '–';
        }
        $d = $date instanceof CarbonInterface ? $date : Carbon::parse($date);
        $d = $d->copy()->setTimezone(config('app.timezone'));

        return $d->format('d').' '.self::MONTHS[$d->month - 1].' '.$d->format('Y');
    }

    public static function dateTime(CarbonInterface|string|null $date): string
    {
        if (! $date) {
            return '–';
        }
        $d = $date instanceof CarbonInterface ? $date : Carbon::parse($date);
        $d = $d->copy()->setTimezone(config('app.timezone'));

        return self::date($d).' '.$d->format('H:i');
    }
}
