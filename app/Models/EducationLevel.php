<?php

namespace App\Models;

use Database\Factories\EducationLevelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 */
class EducationLevel extends Model
{
    /** @use HasFactory<EducationLevelFactory> */
    use HasFactory;

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

    /** @return HasMany<StudentProfile, $this> */
    public function studentProfiles(): HasMany
    {
        return $this->hasMany(
            StudentProfile::class,
            'education_level_id'
        );
    }

    /** @return HasMany<SchoolClass, $this> */
    public function classes(): HasMany
    {
        return $this->hasMany(
            SchoolClass::class,
            'education_level_id'
        );
    }
}
