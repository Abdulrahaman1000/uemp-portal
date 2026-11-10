<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for super admin write requests.
 * - The route group already requires role:super_admin; authorize() repeats the check (defence in depth).
 * - Input keys are camelCase, so validation errors come back under camelCase keys,
 *   which is what the frontend forms read.
 */
abstract class AdminRequest extends FormRequest
{
    /** Input keys whose text is trimmed before validation. @var list<string> */
    protected array $trim = [];

    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::SuperAdmin;
    }

    protected function prepareForValidation(): void
    {
        $clean = [];

        foreach ($this->trim as $key) {
            $value = $this->input($key);

            if (is_string($value)) {
                $value = trim($value);
                // Optional text fields arrive as null or "". Both mean "not set".
                $clean[$key] = $value === '' ? null : $value;
            }
        }

        $this->merge($clean);
    }

    /** Id sent by the frontend as a string, as an integer for queries (null when not numeric). */
    protected function idInput(string $key): ?int
    {
        $value = $this->input($key);

        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }
}
