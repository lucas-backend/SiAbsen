<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentAttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
    ) {}

    /**
     * Riwayat absensi siswa yang sedang login (TIDAK_HADIR dihitung saat baca).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return response()->json([
            'data' => $this->attendance->studentHistory($user),
        ]);
    }
}
