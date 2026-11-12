<?php

namespace App\Http\Controllers\Api\Admin\Concerns;

use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/** Small helpers shared by the super admin CRUD controllers. */
trait ManagesRecords
{
    /**
     * Case-insensitive "contains" search over the given columns (works on PostgreSQL and SQLite).
     *
     * @param  list<string>  $columns  Internal column names (never user input).
     */
    protected function applySearch(Builder $query, ?string $term, array $columns): Builder
    {
        if ($term === null) {
            return $query;
        }

        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';

        return $query->where(function (Builder $q) use ($columns, $like) {
            foreach ($columns as $column) {
                $q->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$like]);
            }
        });
    }

    /**
     * Fill the model and save it. Returns what changed, as ['field' => ['from' => .., 'to' => ..]].
     *
     * @param  array<string, mixed>  $data  snake_case column => value
     * @return array<string, array{from: mixed, to: mixed}>
     */
    protected function applyUpdate(Model $model, array $data): array
    {
        $model->fill($data);

        $changes = [];
        foreach ($model->getDirty() as $field => $to) {
            $changes[$field] = ['from' => $model->getOriginal($field), 'to' => $to];
        }

        if ($changes !== []) {
            $model->save();
        }

        return $changes;
    }

    /**
     * "Cannot delete "X": it still has 2 departments and 1 program." or null when nothing blocks it.
     *
     * @param  array<string, int>  $counts  singular label => how many exist
     */
    protected function blockedMessage(string $name, array $counts): ?string
    {
        $parts = [];

        foreach ($counts as $label => $count) {
            if ($count > 0) {
                $parts[] = $count.' '.Str::plural($label, $count);
            }
        }

        if ($parts === []) {
            return null;
        }

        $last = array_pop($parts);
        $list = $parts === [] ? $last : implode(', ', $parts).' and '.$last;

        return "Cannot delete \"{$name}\": it still has {$list}.";
    }

    /** Runs the delete; a foreign key error that slipped past the checks becomes a clear 422. */
    protected function deleteOrBlock(Model $model, string $name): ?JsonResponse
    {
        try {
            $model->delete();
        } catch (QueryException) {
            return ApiResponse::message("Cannot delete \"{$name}\": it is still in use by other records.", 422);
        }

        return null;
    }
}
