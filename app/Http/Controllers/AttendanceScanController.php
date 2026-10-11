<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\StoreAttendanceScanRequest;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;

class AttendanceScanController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
    ) {}

    /**
     * Proses pemindaian QR siswa untuk sebuah sesi.
     */
    public function store(StoreAttendanceScanRequest $request, AttendanceSession $attendanceSession): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $latitude = $request->validated('latitude');
        $longitude = $request->validated('longitude');
        $qrPayload = $request->validated('qr_payload');

        $record = $this->attendance->scan(
            $user,
            $attendanceSession,
            is_numeric($latitude) ? (float) $latitude : null,
            is_numeric($longitude) ? (float) $longitude : null,
            is_string($qrPayload) ? $qrPayload : null,
        );

        return response()->json([
            'data' => $this->transform($record),
        ], $record->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(AttendanceRecord $record): array
    {
        return [
            'id' => (int) $record->getKey(),
            'session_id' => $record->session_id,
            'student_id' => $record->student_id,
            'status' => $record->status->value,
            'late_minutes' => $record->late_minutes,
            'scanned_at' => $record->scanned_at->toIso8601String(),
            'latitude' => $record->latitude,
            'longitude' => $record->longitude,
            'distance_meters' => $record->distance_meters,
            'location_status' => $record->location_status?->value,
        ];
    }
}
