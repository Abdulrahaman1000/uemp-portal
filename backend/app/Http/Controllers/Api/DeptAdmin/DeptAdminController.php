<?php

namespace App\Http\Controllers\Api\DeptAdmin;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared helpers for the department admin controllers (T2.6).
 * Every query in these controllers must go through DepartmentScope::apply() first.
 */
abstract class DeptAdminController extends Controller
{
    /** Apply an exact-match filter when the query value is a whole number (ids and levels). */
    protected function whereId(Builder $query, Request $request, string $param, string $column): void
    {
        $value = $request->query($param);
        if (is_string($value) && ctype_digit($value)) {
            $query->where($column, (int) $value);
        }
    }

    /**
     * Case-insensitive search. It is always wrapped in its own group so an "or" inside the
     * search can never switch off the department scope that was added before it.
     *
     * @param  list<string>  $columns
     */
    protected function search(Builder $query, Request $request, array $columns): void
    {
        $term = ApiResponse::search($request);
        if ($term === null || $term === '') {
            return;
        }

        $query->where(fn (Builder $group) => LikeSearch::apply($group, $term, $columns));
    }
}
