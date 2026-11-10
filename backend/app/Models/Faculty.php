<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faculty extends Model
{
    /** @var list<string> */
    protected $fillable = ['name', 'code', 'dean'];

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
