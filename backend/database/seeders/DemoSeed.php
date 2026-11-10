<?php

namespace Database\Seeders;

use Carbon\Carbon;

/**
 * Small helpers shared by the demo seeders.
 *
 * NOTE: the grade bands below mirror frontend/client/lib/mock.ts. When the configurable
 * GradingService (config/grading.php, T2.7) is added, the seeders should call it instead.
 */
final class DemoSeed
{
    /** One password for every demo account. Override with SEED_PASSWORD in backend/.env. */
    public static function password(): string
    {
        return env('SEED_PASSWORD') ?: 'demo123';
    }

    /** @return array{0: string, 1: float} [letter grade, grade point] */
    public static function grade(float $total): array
    {
        return match (true) {
            $total >= 70 => ['A', 5.0],
            $total >= 60 => ['B', 4.0],
            $total >= 50 => ['C', 3.0],
            $total >= 45 => ['D', 2.0],
            $total >= 40 => ['E', 1.0],
            default => ['F', 0.0],
        };
    }

    public static function at(string $time): Carbon
    {
        return Carbon::parse($time);
    }
}
