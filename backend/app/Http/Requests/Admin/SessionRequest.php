<?php

namespace App\Http\Requests\Admin;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create or update an academic session: {name, startDate, endDate}.
 * Creating one also creates its First and Second semesters (see SessionController).
 */
class SessionRequest extends AdminRequest
{
    /** A session shorter than this cannot hold two semesters. */
    public const MIN_MONTHS = 6;

    protected array $trim = ['name'];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('session')?->getKey();

        return [
            'name' => [
                'required', 'string', 'max:20',
                'regex:/^\d{4}\/\d{4}$/',
                Rule::unique('academic_sessions', 'name')->ignore($id),
            ],
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.regex' => 'Use the format 2027/2028 for the session name.',
            'name.unique' => 'This session already exists.',
            'startDate.date_format' => 'Use a valid start date (YYYY-MM-DD).',
            'endDate.date_format' => 'Use a valid end date (YYYY-MM-DD).',
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator) {
            $errors = $validator->errors();

            // Name: the second year must follow the first (2027/2028, not 2027/2030).
            if (! $errors->has('name') && preg_match('#^(\d{4})/(\d{4})$#', (string) $this->input('name'), $m)
                && (int) $m[2] !== (int) $m[1] + 1) {
                $validator->errors()->add('name', 'The second year must follow the first, for example 2027/2028.');
            }

            if ($errors->has('startDate') || $errors->has('endDate')) {
                return;
            }

            $start = Carbon::createFromFormat('Y-m-d', $this->input('startDate'))->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', $this->input('endDate'))->startOfDay();

            if ($end->lt($start->copy()->addMonths(self::MIN_MONTHS))) {
                $validator->errors()->add('endDate', 'A session must run for at least '.self::MIN_MONTHS.' months after its start date.');

                return;
            }

            // When editing, the new dates must still contain the semesters already created.
            $session = $this->route('session');
            if ($session) {
                $outside = $session->semesters()
                    ->where(fn ($q) => $q->whereDate('start_date', '<', $start->toDateString())->orWhereDate('end_date', '>', $end->toDateString()))
                    ->exists();

                if ($outside) {
                    $validator->errors()->add('startDate', 'The new dates must still cover all semesters of this session. Edit the semesters first.');
                }
            }
        }];
    }

    /** @return array<string, mixed> */
    public function columns(): array
    {
        $data = $this->validated();

        return ['name' => $data['name'], 'start_date' => $data['startDate'], 'end_date' => $data['endDate']];
    }
}
