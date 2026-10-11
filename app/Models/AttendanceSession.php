<?php

namespace App\Models;

use Database\Factories\AttendanceSessionFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assignment_id
 * @property int $class_id
 * @property Carbon $session_date
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property string $qr_payload
 * @property int $created_by
 * @property float|null $latitude
 * @property float|null $longitude
 */
class AttendanceSession extends Model
{
    /** @use HasFactory<AttendanceSessionFactory> */
    use HasFactory;

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

    /**
     * @param  Builder<AttendanceSession>  $query
     * @return Builder<AttendanceSession>
     */
    public function scopeOnDate(Builder $query, DateTimeInterface|string $date): Builder
    {
        return $query->whereDate('session_date', $date);
    }
}
