<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'matric_no',
        'department_id',
        'program_id',
        'level',
        'graduated_at',
        'phone',
        'address',
        'date_of_birth',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'graduated_at' => 'datetime',
            'date_of_birth' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }
}
