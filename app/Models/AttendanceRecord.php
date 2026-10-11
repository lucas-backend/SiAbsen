<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\LocationStatus;
use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $session_id
 * @property int $student_id
 * @property Carbon $scanned_at
 * @property AttendanceStatus $status
 * @property int|null $late_minutes
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $distance_meters
 * @property LocationStatus|null $location_status
 */
class AttendanceRecord extends Model
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory;

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
            'status' => AttendanceStatus::class,
            'late_minutes' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'distance_meters' => 'integer',
            'location_status' => LocationStatus::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<AttendanceSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(
            AttendanceSession::class,
            'session_id'
        );
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
