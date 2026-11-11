<?php

namespace App\Services;

/**
 * The single place that turns scores into a total, a grade and a grade point.
 * Everything comes from config/grading.php (CA max, exam max and the grade bands),
 * so changing the scale never needs a code change.
 */
class GradingService
{
    public function caMax(): float
    {
        return (float) config('grading.ca_max');
    }

    public function examMax(): float
    {
        return (float) config('grading.exam_max');
    }

    /**
     * Bands from the highest minimum to the lowest.
     *
     * @return list<array{min: int|float, max: int|float, grade: string, point: int|float}>
     */
    public function bands(): array
    {
        $bands = array_values(config('grading.bands', []));
        usort($bands, fn (array $a, array $b) => $b['min'] <=> $a['min']);

        return $bands;
    }

    /**
     * Letter grade and grade point for a total score. A band matches when total >= its min,
     * checked from the top, so a total such as 69.5 falls in B and never between two bands.
     *
     * @return array{0: string, 1: float} [grade, grade point]
     */
    public function grade(float $total): array
    {
        $bands = $this->bands();

        foreach ($bands as $band) {
            if ($total >= $band['min']) {
                return [(string) $band['grade'], (float) $band['point']];
            }
        }

        $lowest = end($bands);

        return [(string) $lowest['grade'], (float) $lowest['point']];
    }

    /**
     * Total, grade and grade point. All three are null until BOTH scores are entered.
     *
     * @return array{total: float|null, grade: string|null, grade_point: float|null}
     */
    public function compute(?float $ca, ?float $exam): array
    {
        if ($ca === null || $exam === null) {
            return ['total' => null, 'grade' => null, 'grade_point' => null];
        }

        $total = round($ca + $exam, 2);
        [$grade, $point] = $this->grade($total);

        return ['total' => $total, 'grade' => $grade, 'grade_point' => $point];
    }

    /**
     * GPA = sum(grade point x course units) / sum(course units), rounded to 2 places.
     * Returns null when there are no units.
     *
     * @param  iterable<array{point: float|int, units: int}>  $rows
     */
    public function gpa(iterable $rows): ?float
    {
        $points = 0.0;
        $units = 0;

        foreach ($rows as $row) {
            $points += (float) $row['point'] * (int) $row['units'];
            $units += (int) $row['units'];
        }

        return $units > 0 ? round($points / $units, 2) : null;
    }
}
