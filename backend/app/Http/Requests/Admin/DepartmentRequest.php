<?php

namespace App\Http\Requests\Admin;

use App\Rules\UniqueIgnoringCase;
use Illuminate\Validation\Rule;

class DepartmentRequest extends AdminRequest
{
    protected array $trim = ['name', 'code', 'head'];

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper($this->input('code'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('department')?->getKey();
        $facultyId = $this->idInput('facultyId');

        // The name only has to be unique inside its faculty.
        $nameRules = ['required', 'string', 'max:255'];
        if ($facultyId !== null) {
            $nameRules[] = new UniqueIgnoringCase('departments', 'name', 'This faculty already has a department with this name.', $id, ['faculty_id' => $facultyId]);
        }

        return [
            'facultyId' => ['required', 'integer', Rule::exists('faculties', 'id')],
            'name' => $nameRules,
            'code' => ['required', 'string', 'max:10', Rule::unique('departments', 'code')->ignore($id)],
            'head' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'facultyId.required' => 'Choose a faculty.',
            'facultyId.integer' => 'Choose a valid faculty.',
            'facultyId.exists' => 'The selected faculty does not exist.',
            'code.unique' => 'A department with this code already exists.',
        ];
    }

    /** @return array<string, mixed> */
    public function columns(): array
    {
        $data = $this->validated();

        return [
            'faculty_id' => (int) $data['facultyId'],
            'name' => $data['name'],
            'code' => $data['code'],
            'head' => $data['head'] ?? null,
        ];
    }
}
