<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EducationLevel extends Model
{
    protected $fillable = [
        'name',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function studentProfiles(): HasMany
    {
        return $this->hasMany(
            StudentProfile::class,
            'education_level_id'
        );
    }

    public function classes(): HasMany
    {
        return $this->hasMany(
            SchoolClass::class,
            'education_level_id'
        );
    }
}
