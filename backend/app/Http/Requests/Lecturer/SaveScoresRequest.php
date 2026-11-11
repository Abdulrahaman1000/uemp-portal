<?php

namespace App\Http\Requests\Lecturer;

use App\Services\GradingService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body: { scores: [ { studentId, caScore, examScore }, ... ] }
 * A score may be null (not entered yet). The maximums come from config/grading.php.
 */
class SaveScoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $grading = app(GradingService::class);

        return [
            'scores' => ['required', 'array', 'min:1', 'max:1000'],
            'scores.*.studentId' => ['required', 'integer', 'distinct'],
            'scores.*.caScore' => ['nullable', 'numeric', 'min:0', 'max:'.$grading->caMax(), 'decimal:0,2'],
            'scores.*.examScore' => ['nullable', 'numeric', 'min:0', 'max:'.$grading->examMax(), 'decimal:0,2'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $grading = app(GradingService::class);

        return [
            'scores.*.caScore.max' => 'CA score cannot be more than '.$grading->caMax().'.',
            'scores.*.examScore.max' => 'Exam score cannot be more than '.$grading->examMax().'.',
            'scores.*.caScore.min' => 'CA score cannot be negative.',
            'scores.*.examScore.min' => 'Exam score cannot be negative.',
            'scores.*.caScore.numeric' => 'CA score must be a number.',
            'scores.*.examScore.numeric' => 'Exam score must be a number.',
            'scores.*.caScore.decimal' => 'CA score can have at most 2 decimal places.',
            'scores.*.examScore.decimal' => 'Exam score can have at most 2 decimal places.',
            'scores.*.studentId.distinct' => 'The same student appears twice.',
        ];
    }
}
