<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ScanAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassStudent;
use App\Models\User;
use App\Services\DynamicQrService;
use App\Services\GeofencingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class AttendanceScanController extends Controller
{
    public function __construct(
        protected GeofencingService $geofencingService,
        protected DynamicQrService $dynamicQrService
    ) {}

    /**
     * Handle an incoming attendance QR scan from a student.
     */
    public function store(ScanAttendanceRequest $request): JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || ! $user->isStudent()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Forbidden. Pengguna bukan siswa.',
                ], 403);
            }

            abort(403, 'Forbidden. Pengguna bukan siswa.');
        }

        $validated = $request->validated();
        $token = (string) $validated['qr_token'];
        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];

        // 1. Verifikasi token QR (mendukung token dinamis bervalidasi waktu dan fallback statis)
        /** @var AttendanceSession|null $session */
        $session = null;

        if (str_contains($token, '.')) {
            $verification = $this->dynamicQrService->verifyToken($token);

            if (! $verification['valid']) {
                $tokenError = $verification['error'] ?? 'Token QR tidak valid.';

                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => $tokenError,
                        'errors' => [
                            'qr_token' => [$tokenError],
                        ],
                    ], 422);
                }

                return back()->withErrors([
                    'qr_token' => $tokenError,
                ])->with('error', $tokenError);
            }

            $session = $verification['session'] ?? null;
        } else {
            // Fallback: pencocokan langsung dengan qr_payload statis
            $session = AttendanceSession::where('qr_payload', $token)->first();
        }

        if (! $session) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Token QR tidak valid atau sesi absensi tidak ditemukan.',
                    'errors' => [
                        'qr_token' => ['Token QR tidak valid atau sesi absensi tidak ditemukan.'],
                    ],
                ], 422);
            }

            return back()->withErrors([
                'qr_token' => 'Token QR tidak valid atau sesi absensi tidak ditemukan.',
            ])->with('error', 'Token QR tidak valid atau sesi absensi tidak ditemukan.');
        }

        // 2. Validasi status dan waktu sesi
        $now = Carbon::now();
        $startAt = Carbon::parse($session->start_at);
        $endAt = Carbon::parse($session->end_at);

        if ($now->lessThan($startAt)) {
            $errorMessage = 'Sesi absensi belum dimulai.';

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $errorMessage,
                    'errors' => [
                        'session' => [$errorMessage],
                    ],
                ], 422);
            }

            return back()->withErrors(['session' => $errorMessage])->with('error', $errorMessage);
        }

        if ($now->greaterThan($endAt)) {
            $errorMessage = 'Sesi absensi sudah berakhir atau telah ditutup oleh guru.';

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $errorMessage,
                    'errors' => [
                        'session' => [$errorMessage],
                    ],
                ], 422);
            }

            return back()->withErrors(['session' => $errorMessage])->with('error', $errorMessage);
        }

        // 3. Validasi keanggotaan kelas aktif siswa
        $isClassMember = ClassStudent::where('class_id', $session->class_id)
            ->where('student_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $isClassMember) {
            $errorMessage = 'Anda tidak terdaftar sebagai siswa aktif di kelas ini.';

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $errorMessage,
                    'errors' => [
                        'student' => [$errorMessage],
                    ],
                ], 403);
            }

            abort(403, $errorMessage);
        }

        // 4. Validasi pencegahan scan ganda (pemeriksaan awal sebelum transaksi)
        $existingRecord = AttendanceRecord::where('session_id', $session->id)
            ->where('student_id', $user->id)
            ->first();

        if ($existingRecord) {
            $duplicateMessage = 'Anda sudah melakukan absensi pada sesi ini.';

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $duplicateMessage,
                    'data' => $existingRecord,
                ], 409);
            }

            return back()->with('warning', $duplicateMessage);
        }

        // 5. Integrasi Geofencing
        try {
            $geoResult = $this->geofencingService->check($latitude, $longitude);
        } catch (InvalidArgumentException $e) {
            $gpsErrorMessage = 'Koordinat GPS tidak valid.';

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $gpsErrorMessage,
                    'errors' => [
                        'gps' => [$gpsErrorMessage],
                    ],
                ], 422);
            }

            return back()->withErrors(['gps' => $gpsErrorMessage])->with('error', $gpsErrorMessage);
        } catch (RuntimeException $e) {
            $schoolConfigMessage = 'Konfigurasi lokasi sekolah belum tersedia. Silakan hubungi admin sekolah.';

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $schoolConfigMessage,
                    'errors' => [
                        'school' => [$schoolConfigMessage],
                    ],
                ], 422);
            }

            return back()->withErrors(['school' => $schoolConfigMessage])->with('error', $schoolConfigMessage);
        }

        // 6. Hitung keterlambatan (menit)
        $lateMinutes = $now->greaterThan($startAt)
            ? (int) $startAt->diffInMinutes($now)
            : 0;

        // 7. Simpan catatan absensi dalam transaksi dengan penanganan race condition (PostgreSQL 23505)
        try {
            DB::beginTransaction();

            $record = AttendanceRecord::create([
                'session_id' => $session->id,
                'student_id' => $user->id,
                'scanned_at' => $now,
                'status' => 'HADIR',
                'late_minutes' => $lateMinutes,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'distance_meters' => $geoResult['distance_meters'],
                'location_status' => $geoResult['location_status'],
            ]);

            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();

            $sqlState = (string) $e->getCode();
            $msg = $e->getMessage();

            if ($sqlState === '23505' || str_contains($msg, 'uq_attendance_records_session_student') || str_contains($msg, 'unique constraint')) {
                $duplicate = AttendanceRecord::where('session_id', $session->id)
                    ->where('student_id', $user->id)
                    ->first();

                $duplicateMessage = 'Anda sudah melakukan absensi pada sesi ini.';

                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => $duplicateMessage,
                        'data' => $duplicate,
                    ], 409);
                }

                return back()->with('warning', $duplicateMessage);
            }

            throw $e;
        }

        // 8. Respons sukses
        $successMessage = $geoResult['location_status'] === 'IN_RANGE'
            ? 'Absensi berhasil dicatat. Lokasi Anda berada dalam radius sekolah.'
            : 'Absensi berhasil dicatat dengan catatan di luar radius sekolah.';

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $successMessage,
                'data' => $record->load(['session.assignment.subject', 'session.schoolClass']),
            ], 201);
        }

        $flashType = $geoResult['location_status'] === 'IN_RANGE' ? 'success' : 'warning';

        return redirect()->route('student.scan')->with($flashType, $successMessage);
    }
}
