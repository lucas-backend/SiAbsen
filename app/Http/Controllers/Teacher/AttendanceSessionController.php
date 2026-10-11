<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreAttendanceSessionRequest;
use App\Http\Requests\Teacher\UpdateManualAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\StudentProfile;
use App\Models\TeacherAssignment;
use App\Services\DynamicQrService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceSessionController extends Controller
{
    public function __construct(
        protected DynamicQrService $dynamicQrService
    ) {}

    /**
     * Display a listing of attendance sessions for the authenticated teacher.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $sessions = AttendanceSession::where('created_by', $user->id)
            ->orWhereHas('assignment', function ($query) use ($user): void {
                $query->where('teacher_id', $user->id);
            })
            ->with(['assignment.subject', 'schoolClass'])
            ->orderByDesc('start_at')
            ->paginate(15);

        return response()->json([
            'message' => 'Daftar sesi absensi berhasil dimuat.',
            'data' => $sessions,
        ]);
    }

    /**
     * Store a newly created attendance session.
     */
    public function store(StoreAttendanceSessionRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isTeacher()) {
            return response()->json([
                'message' => 'Forbidden. Pengguna bukan guru.',
            ], 403);
        }

        $validated = $request->validated();

        // 1. Validasi penugasan aktif milik guru yang sedang login
        /** @var TeacherAssignment|null $assignment */
        $assignment = TeacherAssignment::with('schoolClass')
            ->where('id', $validated['assignment_id'])
            ->where('teacher_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (! $assignment) {
            return response()->json([
                'message' => 'Penugasan tidak ditemukan, tidak aktif, atau bukan milik Anda.',
                'errors' => [
                    'assignment_id' => ['Penugasan tidak ditemukan, tidak aktif, atau bukan milik Anda.'],
                ],
            ], 422);
        }

        // 2. Validasi class_id cocok dengan kelas pada penugasan tersebut
        if ((int) $validated['class_id'] !== (int) $assignment->class_id) {
            return response()->json([
                'message' => 'Kelas yang dipilih tidak sesuai dengan penugasan guru ini.',
                'errors' => [
                    'class_id' => ['Kelas yang dipilih tidak sesuai dengan penugasan guru ini.'],
                ],
            ], 422);
        }

        // 3. Validasi waktu mulai dan berakhir
        $startAt = Carbon::parse($validated['start_at']);
        $endAt = Carbon::parse($validated['end_at']);

        if ($endAt->lessThanOrEqualTo($startAt)) {
            return response()->json([
                'message' => 'Waktu berakhir sesi harus setelah waktu mulai sesi.',
                'errors' => [
                    'end_at' => ['Waktu berakhir sesi harus setelah waktu mulai sesi.'],
                ],
            ], 422);
        }

        $sessionDate = ! empty($validated['session_date'])
            ? Carbon::parse($validated['session_date'])->toDateString()
            : $startAt->toDateString();

        // 4. Validasi tidak ada sesi duplikat untuk penugasan, tanggal, dan rentang waktu yang sama
        $duplicateExists = AttendanceSession::where('assignment_id', $assignment->id)
            ->where('session_date', $sessionDate)
            ->where('start_at', $startAt)
            ->where('end_at', $endAt)
            ->exists();

        if ($duplicateExists) {
            return response()->json([
                'message' => 'Sesi absensi untuk penugasan, tanggal, dan rentang waktu yang sama sudah ada.',
                'errors' => [
                    'session' => ['Sesi absensi untuk penugasan, tanggal, dan rentang waktu yang sama sudah ada.'],
                ],
            ], 422);
        }

        // 5. Buat token QR menggunakan nilai acak kriptografis yang sulit ditebak (256-bit entropy)
        do {
            $qrPayload = bin2hex(random_bytes(32));
        } while (AttendanceSession::where('qr_payload', $qrPayload)->exists());

        // Koordinat default mengacu pada koordinat kelas jika tidak ditentukan
        $schoolClass = $assignment->schoolClass;
        $latitude = $validated['latitude'] ?? ($schoolClass instanceof SchoolClass ? $schoolClass->latitude : null);
        $longitude = $validated['longitude'] ?? ($schoolClass instanceof SchoolClass ? $schoolClass->longitude : null);

        // 6. Simpan sesi dengan penanganan transaksi dan unique constraint concurrency race condition
        try {
            DB::beginTransaction();

            $session = AttendanceSession::create([
                'assignment_id' => $assignment->id,
                'class_id' => $assignment->class_id,
                'session_date' => $sessionDate,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'qr_payload' => $qrPayload,
                'created_by' => $user->id,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();

            $sqlState = (string) $e->getCode();
            $msg = $e->getMessage();

            if ($sqlState === '23505' || str_contains($msg, 'uq_attendance_sessions_assignment_date_time') || str_contains($msg, 'unique constraint')) {
                return response()->json([
                    'message' => 'Sesi absensi untuk penugasan, tanggal, dan rentang waktu yang sama sudah ada.',
                    'errors' => [
                        'session' => ['Sesi absensi untuk penugasan, tanggal, dan rentang waktu yang sama sudah ada.'],
                    ],
                ], 422);
            }

            throw $e;
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Sesi absensi berhasil dibuat.',
                'data' => $session->load(['assignment.subject', 'schoolClass']),
            ], 201);
        }

        return redirect()->route('teacher.class')->with('success', 'Sesi absensi berhasil dibuat.');
    }

    /**
     * Display the specified attendance session.
     */
    public function show(Request $request, AttendanceSession $session): JsonResponse
    {
        $user = $request->user();
        $assignment = $session->assignment;

        // Otorisasi: guru harus merupakan pemilik penugasan
        if (! $assignment instanceof TeacherAssignment || (int) $assignment->teacher_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Forbidden. Anda bukan pemilik penugasan untuk sesi absensi ini.',
            ], 403);
        }

        return response()->json([
            'message' => 'Detail sesi absensi berhasil dimuat.',
            'data' => $session->load(['assignment.subject', 'schoolClass']),
        ]);
    }

    /**
     * End an attendance session safely without modifying schema or deleting history.
     */
    public function end(Request $request, AttendanceSession $session): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $assignment = $session->assignment;

        // Otorisasi: guru adalah pemilik penugasan
        if (! $assignment instanceof TeacherAssignment || (int) $assignment->teacher_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Forbidden. Anda bukan pemilik penugasan untuk sesi absensi ini.',
            ], 403);
        }

        $now = Carbon::now();

        // Jika sesi sudah berakhir sebelumnya, kembalikan status saat ini
        if ($session->isExpired()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Sesi absensi ini sudah berakhir sebelumnya.',
                    'data' => $session->load(['assignment.subject', 'schoolClass']),
                ], 200);
            }

            return redirect()->route('teacher.class')->with('info', 'Sesi absensi ini sudah berakhir sebelumnya.');
        }

        // Akhiri sesi dengan memperbarui end_at menjadi saat ini
        $startAt = Carbon::parse($session->start_at);
        $newEndAt = $now->greaterThan($startAt) ? $now : $startAt;

        $session->update([
            'end_at' => $newEndAt,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Sesi absensi berhasil diakhiri.',
                'data' => $session->fresh()->load(['assignment.subject', 'schoolClass']),
            ], 200);
        }

        return redirect()->route('teacher.class')->with('success', 'Sesi absensi berhasil diakhiri.');
    }

    /**
     * Get active dynamic QR token for the specified attendance session.
     */
    public function qrToken(Request $request, AttendanceSession $session): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isTeacher()) {
            return response()->json([
                'message' => 'Forbidden. Pengguna bukan guru.',
            ], 403);
        }

        $assignment = $session->assignment;

        // Otorisasi: guru harus merupakan pemilik penugasan untuk sesi absensi ini
        if (! $assignment instanceof TeacherAssignment || (int) $assignment->teacher_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Forbidden. Anda bukan pemilik penugasan untuk sesi absensi ini.',
            ], 403);
        }

        $now = Carbon::now();
        $startAt = Carbon::parse($session->start_at);
        $endAt = Carbon::parse($session->end_at);

        if ($now->lessThan($startAt)) {
            return response()->json([
                'message' => 'Sesi absensi belum dimulai.',
                'is_active' => false,
            ], 422);
        }

        if ($now->greaterThan($endAt) || $session->isExpired()) {
            return response()->json([
                'message' => 'Sesi absensi sudah berakhir atau telah ditutup.',
                'is_active' => false,
            ], 422);
        }

        $qrData = $this->dynamicQrService->generateToken($session);

        return response()->json([
            'message' => 'Token QR berhasil dimuat.',
            'data' => [
                'session_id' => $session->id,
                'qr_token' => $qrData['token'],
                'expires_at' => $qrData['expires_at'],
                'expires_in' => $qrData['expires_in'],
                'start_at' => $session->start_at,
                'end_at' => $session->end_at,
                'is_active' => true,
            ],
        ]);
    }

    /**
     * Get attendance monitoring list for all active students in the session's class.
     */
    public function monitor(Request $request, AttendanceSession $session): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isTeacher()) {
            return response()->json([
                'message' => 'Forbidden. Pengguna bukan guru.',
            ], 403);
        }

        $assignment = $session->assignment;

        // Otorisasi: guru harus pemilik penugasan atau pembuat sesi
        $isAuthorized = ($assignment instanceof TeacherAssignment && (int) $assignment->teacher_id === (int) $user->id)
            || (int) $session->created_by === (int) $user->id;

        if (! $isAuthorized) {
            return response()->json([
                'message' => 'Forbidden. Anda bukan pemilik penugasan untuk sesi absensi ini.',
            ], 403);
        }

        // 1. Ambil seluruh siswa aktif di kelas sesi ini
        $classStudents = ClassStudent::with(['student.studentProfile'])
            ->where('class_id', $session->class_id)
            ->where('is_active', true)
            ->get();

        // 2. Ambil catatan absensi untuk sesi ini
        $records = AttendanceRecord::where('session_id', $session->id)
            ->get()
            ->keyBy('student_id');

        // 3. Gabungkan data siswa dan hitung status (BELUM_ABSEN jika tidak ada record)
        /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $studentList */
        $studentList = $classStudents->map(function (ClassStudent $cs) use ($records): array {
            $student = $cs->student;
            /** @var AttendanceRecord|null $record */
            $record = $records->get($cs->student_id);

            $status = $record ? (string) $record->status : 'BELUM_ABSEN';
            $scannedAtFormatted = $record && $record->scanned_at
                ? Carbon::parse($record->scanned_at)->format('H.i')
                : null;
            $isOutOfRange = $record && $record->location_status === 'OUT_OF_RANGE';
            $profile = $student?->studentProfile;

            return [
                'id' => $cs->student_id,
                'name' => $student ? $student->name : 'Siswa',
                'nis' => $profile instanceof StudentProfile ? $profile->student_number : (string) $cs->student_id,
                'status' => $status,
                'time' => $scannedAtFormatted,
                'warn' => $isOutOfRange,
                'location_status' => $record?->location_status,
                'distance_meters' => $record?->distance_meters,
                'record_id' => $record?->id,
                'can_edit' => ! $record || $record->scanned_at === null,
            ];
        });

        // 4. Hitung ringkasan
        $hadirCount = $studentList->where('status', 'HADIR')->count();
        $izinCount = $studentList->where('status', 'IZIN')->count();
        $sakitCount = $studentList->where('status', 'SAKIT')->count();
        $belumHadirCount = $studentList->where('status', 'BELUM_ABSEN')->count();
        $totalCount = $studentList->count();

        return response()->json([
            'message' => 'Data monitoring kehadiran berhasil dimuat.',
            'data' => [
                'session_id' => $session->id,
                'summary' => [
                    'total' => $totalCount,
                    'hadir' => $hadirCount,
                    'belum_hadir' => $belumHadirCount,
                    'izin' => $izinCount,
                    'sakit' => $sakitCount,
                    'izin_sakit' => $izinCount + $sakitCount,
                ],
                'students' => $studentList->values(),
            ],
        ]);
    }

    /**
     * Update manual attendance status (SAKIT or IZIN) by teacher for an unrecorded student.
     */
    public function manualAttendance(UpdateManualAttendanceRequest $request, AttendanceSession $session): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isTeacher()) {
            return response()->json([
                'message' => 'Forbidden. Pengguna bukan guru.',
            ], 403);
        }

        $assignment = $session->assignment;

        // Otorisasi: guru harus pemilik penugasan atau pembuat sesi
        $isAuthorized = ($assignment instanceof TeacherAssignment && (int) $assignment->teacher_id === (int) $user->id)
            || (int) $session->created_by === (int) $user->id;

        if (! $isAuthorized) {
            return response()->json([
                'message' => 'Forbidden. Anda bukan pemilik penugasan untuk sesi absensi ini.',
            ], 403);
        }

        $validated = $request->validated();
        $studentId = (int) $validated['student_id'];
        $status = (string) $validated['status'];

        // Validasi keanggotaan kelas aktif siswa
        $isClassMember = ClassStudent::where('class_id', $session->class_id)
            ->where('student_id', $studentId)
            ->where('is_active', true)
            ->exists();

        if (! $isClassMember) {
            return response()->json([
                'message' => 'Siswa tidak terdaftar sebagai siswa aktif di kelas ini.',
                'errors' => [
                    'student_id' => ['Siswa tidak terdaftar sebagai siswa aktif di kelas ini.'],
                ],
            ], 422);
        }

        // Cek catatan yang sudah ada
        /** @var AttendanceRecord|null $existingRecord */
        $existingRecord = AttendanceRecord::where('session_id', $session->id)
            ->where('student_id', $studentId)
            ->first();

        // Jangan izinkan mengubah siswa yang sudah melakukan scan mandiri (scanned_at != null atau status HADIR)
        if ($existingRecord && ($existingRecord->scanned_at !== null || $existingRecord->status === 'HADIR')) {
            return response()->json([
                'message' => 'Tidak dapat mengubah status siswa yang sudah melakukan scan kehadiran mandiri.',
                'errors' => [
                    'student_id' => ['Siswa sudah melakukan scan kehadiran mandiri.'],
                ],
            ], 422);
        }

        if ($status === 'BELUM_ABSEN') {
            if ($existingRecord) {
                $existingRecord->delete();
            }

            return response()->json([
                'message' => 'Catatan kehadiran manual berhasil dihapus (kembali ke Belum Hadir).',
                'data' => [
                    'student_id' => $studentId,
                    'status' => 'BELUM_ABSEN',
                ],
            ], 200);
        }

        // Simpan status SAKIT atau IZIN dengan transaksi dan penanganan race condition (PostgreSQL 23505)
        try {
            DB::beginTransaction();

            if ($existingRecord) {
                $existingRecord->update([
                    'status' => $status,
                ]);
                $record = $existingRecord;
            } else {
                $record = AttendanceRecord::create([
                    'session_id' => $session->id,
                    'student_id' => $studentId,
                    'status' => $status,
                    'scanned_at' => null,
                    'late_minutes' => null,
                    'latitude' => null,
                    'longitude' => null,
                    'distance_meters' => null,
                    'location_status' => null,
                ]);
            }

            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();

            $sqlState = (string) $e->getCode();
            $msg = $e->getMessage();

            if ($sqlState === '23505' || str_contains($msg, 'uq_attendance_records_session_student') || str_contains($msg, 'unique constraint')) {
                $record = AttendanceRecord::where('session_id', $session->id)
                    ->where('student_id', $studentId)
                    ->first();

                if ($record && $record->scanned_at === null) {
                    $record->update(['status' => $status]);
                } else {
                    return response()->json([
                        'message' => 'Catatan absensi siswa sudah dibuat sebelumnya oleh sistem.',
                    ], 409);
                }
            } else {
                throw $e;
            }
        }

        return response()->json([
            'message' => 'Status kehadiran siswa berhasil diperbarui.',
            'data' => [
                'student_id' => $studentId,
                'status' => $record->status,
                'record_id' => $record->id,
            ],
        ], 200);
    }
}
