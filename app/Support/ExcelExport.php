<?php

namespace App\Support;

use App\Domain\Activity\ActivityLogger;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Ekspor daftar ke Excel (.xlsx) dengan openspout (hemat memori: baris ditulis langsung ke berkas). */
class ExcelExport
{
    /** @param iterable<array> $rows */
    public static function download(string $name, array $headers, iterable $rows): BinaryFileResponse
    {
        $dir = storage_path('app/private/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $path = $dir.'/'.Str::random(24).'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValuesWithStyle($headers, new Style(fontBold: true, backgroundColor: 'DBEAFE')));
        $count = 0;
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(fn ($v) => is_scalar($v) || $v === null ? self::safe($v) : (string) $v, $row)));
            $count++;
        }
        $writer->close();

        app(ActivityLogger::class)->log('ekspor', "Ekspor Excel: {$name} ({$count} baris)");

        return response()->download($path, $name.' '.now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend();
    }

    /** Cegah formula injection di Excel. */
    private static function safe(mixed $v): mixed
    {
        if (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true)) {
            return "'".$v;
        }

        return $v;
    }
}
