<?php

namespace App\Models;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $session_id
 * @property int $student_id
 * @property AttendanceStatus $requested_status
 * @property string|null $reason
 * @property string|null $evidence_path
 * @property AttendanceRequestStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 */
class AttendanceRequest extends Model
{
    protected $fillable = [
        'session_id',
        'student_id',
        'requested_status',
        'reason',
        'evidence_path',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_status' => AttendanceStatus::class,
            'status' => AttendanceRequestStatus::class,
            'reviewed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<AttendanceSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
