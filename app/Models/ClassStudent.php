<?php

namespace App\Models;

use Database\Factories\ClassStudentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $class_id
 * @property int $student_id
 * @property string|null $academic_year
 * @property bool $is_active
 */
class ClassStudent extends Model
{
    /** @use HasFactory<ClassStudentFactory> */
    use HasFactory;

    protected $fillable = [
        'class_id',
        'student_id',
        'academic_year',
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

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @param  Builder<ClassStudent>  $query
     * @return Builder<ClassStudent>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
