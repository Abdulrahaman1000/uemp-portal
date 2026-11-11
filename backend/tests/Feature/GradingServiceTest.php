<?php

namespace Tests\Feature;

use App\Services\GradingService;
use Tests\TestCase;

class GradingServiceTest extends TestCase
{
    public function test_every_band_boundary_matches_the_scale(): void
    {
        $service = app(GradingService::class);
        $expected = [
            100 => ['A', 5.0], 70 => ['A', 5.0], 69 => ['B', 4.0], 60 => ['B', 4.0],
            59 => ['C', 3.0], 50 => ['C', 3.0], 49 => ['D', 2.0], 45 => ['D', 2.0],
            44 => ['E', 1.0], 40 => ['E', 1.0], 39 => ['F', 0.0], 0 => ['F', 0.0],
        ];

        foreach ($expected as $total => $result) {
            $this->assertSame($result, $service->grade((float) $total), "total {$total}");
        }
    }

    public function test_a_total_between_two_whole_numbers_falls_in_the_lower_band(): void
    {
        $this->assertSame(['B', 4.0], app(GradingService::class)->grade(69.5));
        $this->assertSame(['F', 0.0], app(GradingService::class)->grade(39.99));
    }

    public function test_compute_needs_both_scores(): void
    {
        $service = app(GradingService::class);

        $this->assertSame(['total' => 76.0, 'grade' => 'A', 'grade_point' => 5.0], $service->compute(24, 52));
        $this->assertSame(['total' => null, 'grade' => null, 'grade_point' => null], $service->compute(24, null));
        $this->assertSame(['total' => null, 'grade' => null, 'grade_point' => null], $service->compute(null, 52));
    }

    public function test_the_scale_is_read_from_the_config(): void
    {
        config(['grading.bands' => [
            ['min' => 50, 'max' => 100, 'grade' => 'P', 'point' => 1],
            ['min' => 0, 'max' => 49, 'grade' => 'X', 'point' => 0],
        ]]);

        $this->assertSame(['P', 1.0], app(GradingService::class)->grade(50));
        $this->assertSame(['X', 0.0], app(GradingService::class)->grade(49));
    }

    public function test_gpa_is_weighted_by_course_units(): void
    {
        $service = app(GradingService::class);

        // (5 x 3 + 2 x 2 + 0 x 3) / 8 = 2.375 -> 2.38
        $this->assertSame(2.38, $service->gpa([
            ['point' => 5, 'units' => 3], ['point' => 2, 'units' => 2], ['point' => 0, 'units' => 3],
        ]));
        $this->assertNull($service->gpa([]));
    }
}
