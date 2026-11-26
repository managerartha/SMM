<?php

namespace App\Domain\Lms;

use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/** Impor manual dari berkas CSV (pemisah koma/titik koma) atau Excel (.xlsx), baris pertama = judul kolom. */
class CsvLmsSource implements LmsSource
{
    public function __construct(private string $path, private string $originalName) {}

    public function label(): string
    {
        return 'Berkas '.$this->originalName;
    }

    public function classes(): iterable
    {
        return $this->rows(FieldMap::CLASS_FIELDS);
    }

    public function surveys(): iterable
    {
        return $this->rows(FieldMap::SURVEY_FIELDS);
    }

    public function complaints(): iterable
    {
        return $this->rows(FieldMap::COMPLAINT_FIELDS);
    }

    private function rows(array $fields): \Generator
    {
        $ext = strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            $reader = new XlsxReader;
        } else {
            $reader = new CsvReader(new CsvOptions(FIELD_DELIMITER: $this->detectDelimiter()));
        }
        $reader->open($this->path);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $headers = null;
                $n = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $n++;
                    $cells = $row->toArray();
                    if ($headers === null) {
                        $headers = array_map(fn ($h) => (string) $h, $cells);

                        continue;
                    }
                    if (count(array_filter($cells, fn ($c) => $c !== null && $c !== '')) === 0) {
                        continue;
                    }
                    $assoc = [];
                    foreach ($headers as $i => $h) {
                        $assoc[$h] = $cells[$i] ?? null;
                    }
                    yield ['row' => $n, 'data' => FieldMap::normalize($assoc, $fields)];
                }
                break; // hanya sheet pertama
            }
        } finally {
            $reader->close();
        }
    }

    private function detectDelimiter(): string
    {
        $first = (string) fgets(fopen($this->path, 'r'));

        return substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    }
}
