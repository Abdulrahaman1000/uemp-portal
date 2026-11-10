<?php

namespace App\Enums;

enum SemesterName: string
{
    case First = 'First';
    case Second = 'Second';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
