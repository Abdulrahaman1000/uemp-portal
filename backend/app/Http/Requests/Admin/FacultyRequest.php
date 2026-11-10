<?php

namespace App\Http\Requests\Admin;

use App\Rules\UniqueIgnoringCase;
use Illuminate\Validation\Rule;

class FacultyRequest extends AdminRequest
{
    protected array $trim = ['name', 'code', 'dean'];

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
        $id = $this->route('faculty')?->getKey();

        return [
            'name' => ['required', 'string', 'max:255', new UniqueIgnoringCase('faculties', 'name', 'A faculty with this name already exists.', $id)],
            'code' => ['required', 'string', 'max:10', Rule::unique('faculties', 'code')->ignore($id)],
            'dean' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['code.unique' => 'A faculty with this code already exists.'];
    }

    /** @return array<string, mixed> columns for the model */
    public function columns(): array
    {
        $data = $this->validated();

        return ['name' => $data['name'], 'code' => $data['code'], 'dean' => $data['dean'] ?? null];
    }
}
