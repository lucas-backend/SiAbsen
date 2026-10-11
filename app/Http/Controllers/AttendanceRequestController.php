<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceRequestStatus;
use App\Http\Requests\Attendance\ReviewAttendanceRequestRequest;
use App\Http\Requests\Attendance\StoreAttendanceRequestRequest;
use App\Models\AttendanceRequest;
use App\Models\AttendanceSession;
use App\Models\ClassStudent;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AttendanceRequestController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
    ) {}

    /**
     * Daftar pengajuan izin/sakit untuk ditinjau guru (atau semua untuk admin).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AttendanceRequest::class);

        $user = $this->currentUser($request);

        $requests = AttendanceRequest::query()
            ->with([
                'student.studentProfile',
                'session.schoolClass',
                'session.assignment.subject',
                'reviewer',
            ])
            ->when(! $user->isAdmin(), function ($query) use ($user): void {
                $query->whereHas('session', function ($session) use ($user): void {
                    $session->where('created_by', $user->getKey())
                        ->orWhereHas('assignment', fn ($assignment) => $assignment->where('teacher_id', $user->getKey()));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', strtoupper((string) $request->query('status'))))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (AttendanceRequest $attendanceRequest): array => $this->transform($attendanceRequest));

        return response()->json(['data' => $requests]);
    }

    /**
     * Setujui atau tolak pengajuan izin/sakit.
     */
    public function review(ReviewAttendanceRequestRequest $request, AttendanceRequest $attendanceRequest): JsonResponse
    {
        Gate::authorize('review', $attendanceRequest);

        $status = AttendanceRequestStatus::from((string) $request->validated('status'));

        $reviewed = $this->attendance->reviewRequest(
            $this->currentUser($request),
            $attendanceRequest,
            $status,
        );

        $reviewed->load(['student.studentProfile', 'session.schoolClass', 'reviewer']);

        return response()->json(['data' => $this->transform($reviewed)]);
    }

    /**
     * Ajukan izin/sakit untuk sebuah sesi absensi.
     */
    public function store(StoreAttendanceRequestRequest $request): JsonResponse
    {
        $user = $this->currentUser($request);

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
            'status' => AttendanceRequestStatus::Pending,
        ]);

        $attendanceRequest->load('student.studentProfile');

        return response()->json(['data' => $this->transform($attendanceRequest)], 201);
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
    private function transform(AttendanceRequest $attendanceRequest): array
    {
        $session = $attendanceRequest->relationLoaded('session') ? $attendanceRequest->session : null;
        $class = $session?->relationLoaded('schoolClass') ? $session->schoolClass : null;
        $student = $attendanceRequest->relationLoaded('student') ? $attendanceRequest->student : null;

        return [
            'id' => (int) $attendanceRequest->getKey(),
            'session_id' => $attendanceRequest->session_id,
            'student_id' => $attendanceRequest->student_id,
            'student_name' => $student?->name,
            'student_number' => $student?->studentProfile?->student_number,
            'class_name' => $class?->name,
            'session_date' => $session?->session_date?->toDateString(),
            'requested_status' => $attendanceRequest->requested_status->value,
            'requested_status_label' => $attendanceRequest->requested_status->label(),
            'reason' => $attendanceRequest->reason,
            'status' => $attendanceRequest->status->value,
            'status_label' => $attendanceRequest->status->label(),
            'reviewed_by' => $attendanceRequest->reviewed_by,
            'reviewer_name' => $attendanceRequest->reviewer?->name,
            'reviewed_at' => $attendanceRequest->reviewed_at?->toIso8601String(),
            'created_at' => $attendanceRequest->created_at?->toIso8601String(),
        ];
    }
}
