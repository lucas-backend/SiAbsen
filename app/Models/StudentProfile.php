<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property string $student_number
 * @property int $education_level_id
 */
class StudentProfile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'student_number',
        'education_level_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(
            EducationLevel::class,
            'education_level_id'
        );
    }
}
