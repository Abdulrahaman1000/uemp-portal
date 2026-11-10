<?php

namespace App\Http\Requests\Admin;

use App\Enums\SemesterName;
use App\Models\AcademicSession;
use App\Models\Semester;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Create or update one semester: {sessionId, name, startDate, endDate}. */
class SemesterRequest extends AdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sessionId' => ['required', 'integer', Rule::exists('academic_sessions', 'id')],
            'name' => ['required', Rule::in(SemesterName::values())],
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sessionId.required' => 'Choose a session.',
            'sessionId.integer' => 'Choose a valid session.',
            'sessionId.exists' => 'The selected session does not exist.',
            'name.in' => 'The semester must be First or Second.',
            'startDate.date_format' => 'Use a valid start date (YYYY-MM-DD).',
            'endDate.date_format' => 'Use a valid end date (YYYY-MM-DD).',
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator) {
            $errors = $validator->errors();
            $current = $this->route('semester');

            if ($errors->has('sessionId') || $errors->has('name')) {
                return;
            }

            if ($current && (int) $this->input('sessionId') !== (int) $current->academic_session_id) {
                $validator->errors()->add('sessionId', 'A semester cannot be moved to another session.');

                return;
            }

            $session = AcademicSession::find((int) $this->input('sessionId'));

            $duplicate = Semester::where('academic_session_id', $session->id)
                ->where('name', $this->input('name'))
                ->when($current, fn ($q) => $q->where('id', '!=', $current->id))
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('name', 'This session already has a '.$this->input('name').' semester.');
            }

            if ($errors->has('startDate') || $errors->has('endDate')) {
                return;
            }

            $start = Carbon::createFromFormat('Y-m-d', $this->input('startDate'))->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', $this->input('endDate'))->startOfDay();

            if ($end->lte($start)) {
                $validator->errors()->add('endDate', 'The end date must be after the start date.');

                return;
            }

            if ($start->lt($session->start_date) || $end->gt($session->end_date)) {
                $validator->errors()->add('startDate', "The semester must fall inside the session ({$session->start_date->toDateString()} to {$session->end_date->toDateString()}).");

                return;
            }

            $overlaps = Semester::where('academic_session_id', $session->id)
                ->when($current, fn ($q) => $q->where('id', '!=', $current->id))
                ->whereDate('start_date', '<=', $end->toDateString())
                ->whereDate('end_date', '>=', $start->toDateString())
                ->exists();

            if ($overlaps) {
                $validator->errors()->add('startDate', 'These dates overlap another semester of the same session.');
            }
        }];
    }

    /** @return array<string, mixed> */
    public function columns(): array
    {
        $data = $this->validated();

        return [
            'academic_session_id' => (int) $data['sessionId'],
            'name' => $data['name'],
            'start_date' => $data['startDate'],
            'end_date' => $data['endDate'],
        ];
    }
}
