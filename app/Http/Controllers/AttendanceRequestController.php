<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\StoreAttendanceRequestRequest;
use App\Models\AttendanceRequest;
use App\Models\AttendanceSession;
use App\Models\ClassStudent;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AttendanceRequestController extends Controller
{
    /**
     * Ajukan izin/sakit untuk sebuah sesi absensi.
     */
    public function store(StoreAttendanceRequestRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $session = AttendanceSession::query()->findOrFail((int) $request->validated('session_id'));

        $isInClass = ClassStudent::query()
            ->where('class_id', $session->class_id)
            ->where('student_id', $user->getKey())
            ->where('is_active', true)
            ->exists();

        abort_unless($isInClass, 403);

        $attendanceRequest = AttendanceRequest::query()->create([
            'session_id' => $session->getKey(),
            'student_id' => $user->getKey(),
            'requested_status' => $request->validated('requested_status'),
            'reason' => $request->validated('reason'),
        ]);

        return response()->json([
            'data' => [
                'id' => (int) $attendanceRequest->getKey(),
                'session_id' => $attendanceRequest->session_id,
                'requested_status' => $attendanceRequest->requested_status->value,
                'status' => $attendanceRequest->status->value,
                'reason' => $attendanceRequest->reason,
                'created_at' => $attendanceRequest->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
