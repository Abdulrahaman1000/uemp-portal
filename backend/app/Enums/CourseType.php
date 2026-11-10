<?php

namespace App\Enums;

enum CourseType: string
{
    case Core = 'core';
    case Elective = 'elective';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
