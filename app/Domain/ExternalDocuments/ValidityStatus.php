<?php

namespace App\Domain\ExternalDocuments;

use App\Support\Settings;
use Carbon\CarbonInterface;

enum ValidityStatus: string
{
    case Valid = 'berlaku';
    case Soon = 'segera';
    case Expired = 'kedaluwarsa';
    case NoExpiry = 'tanpa_masa';

    public static function for(?CarbonInterface $expiresAt): self
    {
        if (! $expiresAt) {
            return self::NoExpiry;
        }
        $today = now()->startOfDay();
        if ($expiresAt->copy()->startOfDay()->lt($today)) {
            return self::Expired;
        }
        $soonDays = (int) Settings::get('reminder.external_soon_days', 90);

        return $today->diffInDays($expiresAt->copy()->startOfDay()) <= $soonDays ? self::Soon : self::Valid;
    }

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Berlaku',
            self::Soon => 'Segera berakhir',
            self::Expired => 'Kedaluwarsa',
            self::NoExpiry => 'Berlaku (tanpa masa berlaku)',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Valid, self::NoExpiry => 'green',
            self::Soon => 'amber',
            self::Expired => 'red',
        };
    }
}
