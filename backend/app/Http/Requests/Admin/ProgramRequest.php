<?php

namespace App\Http\Requests\Admin;

use App\Rules\UniqueIgnoringCase;
use Illuminate\Validation\Rule;

class ProgramRequest extends AdminRequest
{
    protected array $trim = ['name', 'award'];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('program')?->getKey();
        $departmentId = $this->idInput('departmentId');

        $nameRules = ['required', 'string', 'max:255'];
        if ($departmentId !== null) {
            $nameRules[] = new UniqueIgnoringCase('programs', 'name', 'This department already has a program with this name.', $id, ['department_id' => $departmentId]);
        }

        return [
            'departmentId' => ['required', 'integer', Rule::exists('departments', 'id')],
            'name' => $nameRules,
            'award' => ['required', 'string', 'max:20'],
            'durationYears' => ['required', 'integer', 'between:1,7'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'departmentId.required' => 'Choose a department.',
            'departmentId.integer' => 'Choose a valid department.',
            'departmentId.exists' => 'The selected department does not exist.',
            'durationYears.between' => 'The duration must be between 1 and 7 years.',
            'durationYears.integer' => 'The duration must be a whole number of years.',
        ];
    }

    /** @return array<string, mixed> */
    public function columns(): array
    {
        $data = $this->validated();

        return [
            'department_id' => (int) $data['departmentId'],
            'name' => $data['name'],
            'award' => $data['award'],
            'duration_years' => (int) $data['durationYears'],
        ];
    }
}
