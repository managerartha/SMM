<?php

namespace App\Domain\Lms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * API baca-saja LMS (kontrak di docs/LMS_ADAPTER.md):
 *   GET {SMM_LMS_API_URL}/kelas?sejak=YYYY-MM-DD   → {"data":[{"kode_kelas":..., ...}]}
 *   GET {SMM_LMS_API_URL}/survei?sejak=YYYY-MM-DD  → {"data":[{"id_respons":..., "kode_kelas":..., ...}]}
 * Header: Authorization: Bearer {SMM_LMS_API_TOKEN}. Kunci JSON sama dengan judul kolom impor CSV.
 */
class ApiLmsSource implements LmsSource
{
    public function __construct(private string $baseUrl, private string $token, private ?string $since = null) {}

    public static function configured(): bool
    {
        return (string) config('smm.lms.api_url') !== '' && (string) config('smm.lms.api_token') !== '';
    }

    public static function fromConfig(?string $since = null): self
    {
        return new self(rtrim((string) config('smm.lms.api_url'), '/'), (string) config('smm.lms.api_token'), $since);
    }

    public function label(): string
    {
        return 'API LMS';
    }

    public function classes(): iterable
    {
        return $this->fetch('kelas', FieldMap::CLASS_FIELDS);
    }

    public function surveys(): iterable
    {
        return $this->fetch('survei', FieldMap::SURVEY_FIELDS);
    }

    public function complaints(): iterable
    {
        return $this->fetch('keluhan', FieldMap::COMPLAINT_FIELDS);
    }

    private function fetch(string $path, array $fields): \Generator
    {
        $res = Http::withToken($this->token)->acceptJson()->timeout(60)
            ->get($this->baseUrl.'/'.$path, array_filter(['sejak' => $this->since]));
        if (! $res->successful()) {
            throw new RuntimeException("API LMS gagal ({$path}): HTTP {$res->status()}");
        }
        foreach ((array) $res->json('data', []) as $i => $item) {
            yield ['row' => $i + 1, 'data' => FieldMap::normalize((array) $item, $fields)];
        }
    }
}
