<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * "Faculty of Science" and "faculty of science" are the same name.
 * PostgreSQL unique indexes are case sensitive, so this rule checks it in the app.
 */
class UniqueIgnoringCase implements ValidationRule
{
    /**
     * @param  string  $table  Internal table name (never user input).
     * @param  string  $column  Internal column name (never user input).
     * @param  array<string, mixed>  $scope  Extra equality filters, e.g. ['faculty_id' => 3].
     */
    public function __construct(
        private string $table,
        private string $column,
        private string $message,
        private mixed $ignoreId = null,
        private array $scope = [],
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $query = DB::table($this->table)
            ->whereRaw("LOWER({$this->column}) = ?", [mb_strtolower($value)]);

        foreach ($this->scope as $column => $scopeValue) {
            $query->where($column, $scopeValue);
        }

        if ($this->ignoreId !== null) {
            $query->where('id', '!=', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail($this->message);
        }
    }
}
