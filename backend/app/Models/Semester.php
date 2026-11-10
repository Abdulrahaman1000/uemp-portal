<?php

namespace App\Models;

use App\Enums\SemesterName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Semester extends Model
{
    /** @var list<string> */
    protected $fillable = ['academic_session_id', 'name', 'start_date', 'end_date', 'is_current'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'name' => SemesterName::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }
}
