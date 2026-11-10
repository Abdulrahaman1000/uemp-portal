<?php

/*
|--------------------------------------------------------------------------
| Grading scale (single source of truth)
|--------------------------------------------------------------------------
| CA is marked out of ca_max and the exam out of exam_max (they add up to 100).
| Each band is inclusive: min <= total score <= max.
| GPA = sum(grade point x course units) / sum(course units).
| The GradingService (T2.7) and the admin "grading scale" page both read this file.
*/

return [
    'ca_max' => 30,
    'exam_max' => 70,

    'bands' => [
        ['min' => 70, 'max' => 100, 'grade' => 'A', 'point' => 5],
        ['min' => 60, 'max' => 69, 'grade' => 'B', 'point' => 4],
        ['min' => 50, 'max' => 59, 'grade' => 'C', 'point' => 3],
        ['min' => 45, 'max' => 49, 'grade' => 'D', 'point' => 2],
        ['min' => 40, 'max' => 44, 'grade' => 'E', 'point' => 1],
        ['min' => 0, 'max' => 39, 'grade' => 'F', 'point' => 0],
    ],

    'gpa_formula' => 'GPA = sum(grade point x course units) / sum(course units)',
];
