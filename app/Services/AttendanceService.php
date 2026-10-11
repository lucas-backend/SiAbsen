<?php

namespace App\Services;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRequest;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\TeacherAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Aturan domain absensi: membuka sesi, memproses scan (jendela, keterlambatan,
 * geofence, idempotensi), serta menghitung TIDAK_HADIR saat pembacaan.
 */
class AttendanceService
{
    public function __construct(
        private readonly GeofenceService $geofence,
    ) {}

    /**
     * Buka sesi absensi untuk sebuah penugasan guru.
     *
     * @param  array<string, mixed>  $data
     */
    public function openSession(User $creator, array $data): AttendanceSession
    {
        $assignment = TeacherAssignment::query()->findOrFail((int) $data['assignment_id']);
        $timezone = (string) config('attendance.timezone');

        $startAt = CarbonImmutable::parse((string) $data['start_at'], $timezone);

        $endAt = isset($data['end_at'])
            ? CarbonImmutable::parse((string) $data['end_at'], $timezone)
            : $startAt->addMinutes((int) config('attendance.default_duration_minutes', 120));

        if ($endAt->lessThanOrEqualTo($startAt)) {
            throw ValidationException::withMessages([
                'end_at' => __('Waktu selesai harus setelah waktu mulai.'),
            ]);
        }

        $sessionDate = isset($data['session_date'])
            ? CarbonImmutable::parse((string) $data['session_date'], $timezone)->toDateString()
            : $startAt->setTimezone($timezone)->toDateString();

        $class = $assignment->schoolClass;

        return AttendanceSession::query()->create([
            'assignment_id' => $assignment->getKey(),
            'class_id' => $assignment->class_id,
            'session_date' => $sessionDate,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'qr_payload' => $this->generateQrPayload(),
            'created_by' => $creator->getKey(),
            'latitude' => $data['latitude'] ?? $class?->latitude,
            'longitude' => $data['longitude'] ?? $class?->longitude,
        ]);
    }

    /**
     * Hasilkan payload QR yang belum dipakai sesi lain.
     */
    public function generateQrPayload(): string
    {
        do {
            $payload = (string) Str::uuid();
        } while (AttendanceSession::query()->where('qr_payload', $payload)->exists());

        return $payload;
    }

    /**
     * Proses pemindaian siswa terhadap sebuah sesi.
     *
     * Idempoten: pemindaian berulang mengembalikan record yang sama.
     */
    public function scan(
        User $student,
        AttendanceSession $session,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $qrPayload = null,
    ): AttendanceRecord {
        if ($qrPayload !== null && ! hash_equals($session->qr_payload, $qrPayload)) {
            throw ValidationException::withMessages([
                'qr_payload' => __('Payload QR tidak cocok dengan sesi ini.'),
            ]);
        }

        $now = CarbonImmutable::now();
        $windowOpensAt = $session->start_at->subMinutes(
            (int) config('attendance.scan_window.opens_before_minutes', 15)
        );

        if ($now->lessThan($windowOpensAt)) {
            throw ValidationException::withMessages([
                'scan' => __('Jendela absensi belum dibuka.'),
            ]);
        }

        if ($now->greaterThan($session->end_at)) {
            throw ValidationException::withMessages([
                'scan' => __('Jendela absensi telah berakhir.'),
            ]);
        }

        $lateDeadline = $session->start_at->addMinutes(
            (int) config('attendance.late_tolerance_minutes', 15)
        );

        if ($now->lessThanOrEqualTo($lateDeadline)) {
            $status = AttendanceStatus::Hadir;
            $lateMinutes = 0;
        } else {
            $status = AttendanceStatus::Terlambat;
            $lateMinutes = (int) $session->start_at->diffInMinutes($now);
        }

        $geofence = $this->geofence->evaluate($session, $latitude, $longitude);

        return AttendanceRecord::query()->firstOrCreate(
            [
                'session_id' => $session->getKey(),
                'student_id' => $student->getKey(),
            ],
            [
                'scanned_at' => $now,
                'status' => $status,
                'late_minutes' => $lateMinutes,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'distance_meters' => $geofence['distance_meters'],
                'location_status' => $geofence['status']->value,
            ],
        );
    }

    /**
     * Siswa aktif pada kelas sesi yang belum memiliki record (TIDAK_HADIR).
     *
     * @return array<int, array<string, mixed>>
     */
    public function computeAbsent(AttendanceSession $session): array
    {
        $class = $session->schoolClass;

        if (! $class instanceof SchoolClass) {
            return [];
        }

        $presentIds = $session->records()->pluck('student_id')->all();

        return $class->activeStudents()
            ->whereNotIn('users.id', $presentIds)
            ->with('studentProfile')
            ->get()
            ->map(fn (User $student) => [
                'student_id' => (int) $student->getKey(),
                'name' => $student->name,
                'student_number' => $student->studentProfile?->student_number,
                'status' => AttendanceStatus::TidakHadir->value,
            ])
            ->all();
    }

    /**
     * Ringkasan seluruh siswa aktif kelas untuk sebuah sesi, menghitung
     * TIDAK_HADIR secara read-time.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sessionSummary(AttendanceSession $session): array
    {
        $class = $session->schoolClass;

        if (! $class instanceof SchoolClass) {
            return [];
        }

        $records = $session->records()->get()->keyBy('student_id');

        return $class->activeStudents()
            ->with('studentProfile')
            ->get()
            ->map(function (User $student) use ($records) {
                $record = $records->get($student->getKey());
                $status = $record instanceof AttendanceRecord ? $record->status : AttendanceStatus::TidakHadir;

                return [
                    'student_id' => (int) $student->getKey(),
                    'name' => $student->name,
                    'student_number' => $student->studentProfile?->student_number,
                    'status' => $status->value,
                    'late_minutes' => $record instanceof AttendanceRecord ? $record->late_minutes : null,
                    'scanned_at' => $record instanceof AttendanceRecord ? $record->scanned_at->toIso8601String() : null,
                    'location_status' => $record instanceof AttendanceRecord ? $record->location_status?->value : null,
                    'distance_meters' => $record instanceof AttendanceRecord ? $record->distance_meters : null,
                ];
            })
            ->all();
    }

    /**
     * Riwayat absensi seorang siswa, menghitung TIDAK_HADIR saat pembacaan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function studentHistory(User $student): array
    {
        $classIds = $student->classStudents()->active()->pluck('class_id');

        return AttendanceSession::query()
            ->whereIn('class_id', $classIds)
            ->with(['records' => fn ($query) => $query->where('student_id', $student->getKey())])
            ->orderByDesc('start_at')
            ->get()
            ->map(function (AttendanceSession $session) use ($student) {
                $record = $session->records->firstWhere('student_id', $student->getKey());
                $status = $record instanceof AttendanceRecord ? $record->status : AttendanceStatus::TidakHadir;

                return [
                    'session_id' => (int) $session->getKey(),
                    'session_date' => $session->session_date->toDateString(),
                    'start_at' => $session->start_at->toIso8601String(),
                    'end_at' => $session->end_at->toIso8601String(),
                    'status' => $status->value,
                    'late_minutes' => $record instanceof AttendanceRecord ? $record->late_minutes : null,
                    'scanned_at' => $record instanceof AttendanceRecord ? $record->scanned_at->toIso8601String() : null,
                    'location_status' => $record instanceof AttendanceRecord ? $record->location_status?->value : null,
                    'distance_meters' => $record instanceof AttendanceRecord ? $record->distance_meters : null,
                ];
            })
            ->all();
    }

    /**
     * Tinjau (setujui/tolak) pengajuan izin/sakit.
     *
     * Saat disetujui, catatan kehadiran siswa untuk sesi tersebut ditulis
     * atau diperbarui menjadi IZIN/SAKIT. Audit peninjau selalu dicatat.
     */
    public function reviewRequest(
        User $reviewer,
        AttendanceRequest $attendanceRequest,
        AttendanceRequestStatus $status,
    ): AttendanceRequest {
        if ($attendanceRequest->status !== AttendanceRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => __('Pengajuan ini sudah ditinjau.'),
            ]);
        }

        return DB::transaction(function () use ($reviewer, $attendanceRequest, $status): AttendanceRequest {
            $reviewedAt = CarbonImmutable::now();

            $attendanceRequest->forceFill([
                'status' => $status,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => $reviewedAt,
            ])->save();

            $requestedStatus = $attendanceRequest->requested_status;

            if ($status === AttendanceRequestStatus::Approved) {
                AttendanceRecord::query()->updateOrCreate(
                    [
                        'session_id' => $attendanceRequest->session_id,
                        'student_id' => $attendanceRequest->student_id,
                    ],
                    [
                        'scanned_at' => $reviewedAt,
                        'status' => $requestedStatus,
                        'late_minutes' => null,
                        'latitude' => null,
                        'longitude' => null,
                        'distance_meters' => null,
                        'location_status' => null,
                    ],
                );
            }

            return $attendanceRequest->refresh();
        });
    }
}
