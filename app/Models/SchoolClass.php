<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $education_level_id
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $radius_meters
 * @property CarbonInterface|string $created_at
 * @property CarbonInterface|string $updated_at
 * @property-read EducationLevel|null $educationLevel
 */
class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'name',
        'education_level_id',
        'latitude',
        'longitude',
        'radius_meters',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<EducationLevel, $this>
     */
    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(
            EducationLevel::class,
            'education_level_id'
        );
    }

    /**
     * @return HasMany<ClassStudent, $this>
     */
    public function classStudents(): HasMany
    {
        return $this->hasMany(ClassStudent::class, 'class_id');
    }

    /**
     * @return HasMany<TeacherAssignment, $this>
     */
    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'class_id');
    }

    /**
     * @return HasMany<AttendanceSession, $this>
     */
    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(
            AttendanceSession::class,
            'class_id'
        );
    }
}
