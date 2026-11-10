<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Registration extends Model
{
    /** @var list<string> */
    protected $fillable = ['student_id', 'semester_id', 'registered_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['registered_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /** Courses registered in this record (pivot table registration_items, unique per course). */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'registration_items', 'registration_id', 'course_id')
            ->withTimestamps();
    }
}
