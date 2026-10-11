<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assignment_id
 * @property int $class_id
 * @property CarbonInterface|string $session_date
 * @property CarbonInterface|string $start_at
 * @property CarbonInterface|string $end_at
 * @property string $qr_payload
 * @property int $created_by
 * @property float|null $latitude
 * @property float|null $longitude
 * @property CarbonInterface|string $created_at
 * @property CarbonInterface|string $updated_at
 * @property-read TeacherAssignment|null $assignment
 * @property-read SchoolClass|null $schoolClass
 * @property-read User|null $creator
 */
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

    /**
     * @return BelongsTo<TeacherAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(
            TeacherAssignment::class,
            'assignment_id'
        );
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(
            AttendanceRecord::class,
            'session_id'
        );
    }

    public function isActive(): bool
    {
        $now = Carbon::now();
        $start = Carbon::parse($this->start_at);
        $end = Carbon::parse($this->end_at);

        return $start->lte($now) && $end->gte($now);
    }

    public function isExpired(): bool
    {
        return Carbon::parse($this->end_at)->isPast();
    }
}
