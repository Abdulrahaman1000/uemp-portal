<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/** Case-insensitive "contains" search over a few columns. Works on PostgreSQL (Neon) and SQLite (tests). */
class LikeSearch
{
    /** @param  list<string>  $columns  Column names written in code (never user input). */
    public static function apply(Builder $query, ?string $term, array $columns): Builder
    {
        if ($term === null || $term === '') {
            return $query;
        }

        $pattern = '%'.strtolower(addcslashes($term, '%_\\')).'%';

        return $query->where(function (Builder $inner) use ($columns, $pattern) {
            foreach ($columns as $column) {
                $inner->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '\\'", [$pattern]);
            }
        });
    }
}
