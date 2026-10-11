<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\StoreAttendanceSessionRequest;
use App\Models\AttendanceSession;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AttendanceSessionController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
    ) {}

    /**
     * Daftar sesi absensi milik guru (atau semua sesi untuk admin).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        $sessions = AttendanceSession::query()
            ->with(['schoolClass', 'assignment.subject'])
            ->when(! $user->isAdmin(), function ($query) use ($user) {
                $query->whereHas('assignment', fn ($assignment) => $assignment->where('teacher_id', $user->getKey()));
            })
            ->when($request->filled('date'), fn ($query) => $query->onDate((string) $request->query('date')))
            ->orderByDesc('start_at')
            ->get()
            ->map(fn (AttendanceSession $session): array => $this->transform($session));

        return response()->json(['data' => $sessions]);
    }

    /**
     * Buka sesi absensi baru untuk penugasan guru.
     */
    public function store(StoreAttendanceSessionRequest $request): JsonResponse
    {
        $assignment = TeacherAssignment::query()->findOrFail((int) $request->validated('assignment_id'));

        Gate::authorize('view', $assignment);

        $session = $this->attendance->openSession($this->currentUser($request), $request->validated());

        return response()->json(['data' => $this->transform($session)], 201);
    }

    /**
     * Tampilkan sesi beserta ringkasan kehadiran (termasuk TIDAK_HADIR).
     */
    public function show(Request $request, AttendanceSession $attendanceSession): JsonResponse
    {
        Gate::authorize('view', $attendanceSession);

        return response()->json([
            'data' => [
                'session' => $this->transform($attendanceSession->load(['schoolClass', 'assignment.subject'])),
                'participants' => $this->attendance->sessionSummary($attendanceSession),
            ],
        ]);
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(AttendanceSession $session): array
    {
        $class = $session->relationLoaded('schoolClass') ? $session->schoolClass : null;

        return [
            'id' => (int) $session->getKey(),
            'assignment_id' => $session->assignment_id,
            'class_id' => $session->class_id,
            'class_name' => $class?->name,
            'session_date' => $session->session_date->toDateString(),
            'start_at' => $session->start_at->toIso8601String(),
            'end_at' => $session->end_at->toIso8601String(),
            'qr_payload' => $session->qr_payload,
            'created_by' => $session->created_by,
            'latitude' => $session->latitude,
            'longitude' => $session->longitude,
        ];
    }
}
