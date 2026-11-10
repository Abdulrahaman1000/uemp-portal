<?php

namespace App\Http\Requests\Admin;

use App\Enums\CourseType;
use App\Enums\SemesterName;
use App\Models\Lecturer;
use App\Models\Program;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create and update a course. Input keys are camelCase (what the frontend sends), so the
 * validation errors come back under the same camelCase keys.
 * Access is already limited to super admins by the route group.
 */
class CourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            // "csc 301" and "CSC301" are the same course code.
            $this->merge(['code' => strtoupper(preg_replace('/\s+/', '', trim($this->input('code'))))]);
        }
        if (is_string($this->input('title'))) {
            $this->merge(['title' => trim($this->input('title'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $course = $this->route('course');

        return [
            'code' => ['required', 'string', 'max:12', Rule::unique('courses', 'code')->ignore($course?->id)],
            'title' => ['required', 'string', 'max:255'],
            'units' => ['required', 'integer', 'between:1,6'],
            'level' => ['required', 'integer', Rule::in([100, 200, 300, 400, 500, 600])],
            'semester' => ['required', Rule::in(SemesterName::values())],
            'type' => ['required', Rule::in(CourseType::values())],
            'departmentId' => ['required', 'integer', 'exists:departments,id'],
            'programId' => ['required', 'integer', 'exists:programs,id'],
            'lecturerId' => ['nullable', 'integer', 'exists:lecturers,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['departmentId', 'programId', 'lecturerId'])) {
                return;
            }

            $departmentId = $this->integer('departmentId');

            $programInDepartment = Program::whereKey($this->integer('programId'))
                ->where('department_id', $departmentId)->exists();
            if (! $programInDepartment) {
                $validator->errors()->add('programId', 'The selected program does not belong to the selected department.');
            }

            if ($this->filled('lecturerId')) {
                $lecturerInDepartment = Lecturer::whereKey($this->integer('lecturerId'))
                    ->where('department_id', $departmentId)->exists();
                if (! $lecturerInDepartment) {
                    $validator->errors()->add('lecturerId', 'The lecturer must belong to the same department as the course.');
                }
            }
        });
    }

    /** The course columns, mapped from the camelCase input. @return array<string, mixed> */
    public function courseAttributes(): array
    {
        return [
            'code' => $this->validated('code'),
            'title' => $this->validated('title'),
            'units' => (int) $this->validated('units'),
            'level' => (int) $this->validated('level'),
            'semester' => $this->validated('semester'),
            'type' => $this->validated('type'),
            'department_id' => (int) $this->validated('departmentId'),
            'program_id' => (int) $this->validated('programId'),
        ];
    }
}
