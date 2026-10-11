<?php

namespace App\Models;

use Database\Factories\TeacherAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $teacher_id
 * @property int $class_id
 * @property int $subject_id
 * @property bool $is_active
 */
class TeacherAssignment extends Model
{
    /** @use HasFactory<TeacherAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'class_id',
        'subject_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    /** @return HasMany<AttendanceSession, $this> */
    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(
            AttendanceSession::class,
            'assignment_id'
        );
    }

    /**
     * @param  Builder<TeacherAssignment>  $query
     * @return Builder<TeacherAssignment>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
