<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'session_id',
        'student_id',
        'scanned_at',
        'status',
        'late_minutes',
        'latitude',
        'longitude',
        'distance_meters',
        'location_status',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'immutable_datetime',
            'late_minutes' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'distance_meters' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(
            AttendanceSession::class,
            'session_id'
        );
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
