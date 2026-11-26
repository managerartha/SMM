<?php

namespace App\Domain\Risks;

/** Level risiko dari skor Kemungkinan × Dampak (1–25): 1–5 rendah, 6–11 sedang, 12–25 tinggi. */
enum RiskLevel: string
{
    case Low = 'rendah';
    case Medium = 'sedang';
    case High = 'tinggi';

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 12 => self::High,
            $score >= 6 => self::Medium,
            default => self::Low,
        };
    }

    public static function score(int $likelihood, int $impact): int
    {
        if ($likelihood < 1 || $likelihood > 5 || $impact < 1 || $impact > 5) {
            throw new \InvalidArgumentException('Kemungkinan dan dampak harus 1–5.');
        }

        return $likelihood * $impact;
    }

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Rendah',
            self::Medium => 'Sedang',
            self::High => 'Tinggi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'green',
            self::Medium => 'amber',
            self::High => 'red',
        };
    }
}
