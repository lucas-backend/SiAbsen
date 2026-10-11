<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    protected $fillable = [
        'assignment_id',
        'class_id',
        'session_date',
        'start_at',
        'end_at',
        'qr_payload',
        'created_by',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'start_at' => 'immutable_datetime',
            'end_at' => 'immutable_datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<TeacherAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(
            TeacherAssignment::class,
            'assignment_id'
        );
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<AttendanceRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(
            AttendanceRecord::class,
            'session_id'
        );
    }
}
