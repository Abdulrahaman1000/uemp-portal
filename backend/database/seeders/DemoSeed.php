<?php

namespace Database\Seeders;

use App\Services\GradingService;
use Carbon\Carbon;

/**
 * Small helpers shared by the demo seeders.
 * Grades come from the GradingService, which reads config/grading.php (single source of truth).
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
        return app(GradingService::class)->grade($total);
    }

    public static function at(string $time): Carbon
    {
        return Carbon::parse($time);
    }
}
