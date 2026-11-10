<?php

namespace App\Models;

use App\Enums\CourseType;
use App\Enums\SemesterName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /** The lecturer assignment of the CURRENT academic session (null when nobody is assigned). */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(CourseAssignment::class)
            ->whereHas('session', fn (Builder $query) => $query->where('is_current', true));
    }

    public function resultSubmissions(): HasMany
    {
        return $this->hasMany(ResultSubmission::class);
    }
}
