<?php

namespace App\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 */
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /** @return HasMany<TeacherAssignment, $this> */
    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(
            TeacherAssignment::class,
            'subject_id'
        );
    }
}
