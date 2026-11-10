<?php

namespace App\Models;

use App\Enums\CourseType;
use App\Enums\SemesterName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    /** @var list<string> */
    protected $fillable = ['code', 'title', 'units', 'level', 'semester', 'type', 'department_id', 'program_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'units' => 'integer',
            'level' => 'integer',
            'semester' => SemesterName::class,
            'type' => CourseType::class,
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CourseAssignment::class);
    }

    public function resultSubmissions(): HasMany
    {
        return $this->hasMany(ResultSubmission::class);
    }
}
