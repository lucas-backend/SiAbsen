<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
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
