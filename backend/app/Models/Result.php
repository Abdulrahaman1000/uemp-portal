<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'result_submission_id',
        'student_id',
        'ca_score',
        'exam_score',
        'total_score',
        'grade',
        'grade_point',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ca_score' => 'decimal:2',
            'exam_score' => 'decimal:2',
            'total_score' => 'decimal:2',
            'grade_point' => 'decimal:1',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResultSubmission::class, 'result_submission_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
