<?php

namespace App\Models;

use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory;

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

    /** @return BelongsTo<EducationLevel, $this> */
    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(
            EducationLevel::class,
            'education_level_id'
        );
    }

    /** @return HasMany<ClassStudent, $this> */
    public function classStudents(): HasMany
    {
        return $this->hasMany(ClassStudent::class, 'class_id');
    }

    /**
     * Users currently placed in this class.
     *
     * @return BelongsToMany<User, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_students', 'class_id', 'student_id')
            ->wherePivot('is_active', true)
            ->withPivot(['academic_year', 'is_active']);
    }

    /**
     * Alias for the active students relation.
     *
     * @return BelongsToMany<User, $this>
     */
    public function activeStudents(): BelongsToMany
    {
        return $this->students();
    }

    /** @return HasMany<TeacherAssignment, $this> */
    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'class_id');
    }

    /** @return HasMany<AttendanceSession, $this> */
    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(
            AttendanceSession::class,
            'class_id'
        );
    }
}
