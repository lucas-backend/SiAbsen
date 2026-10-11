<?php

use App\Http\Controllers\Student\AttendanceScanController;
use App\Http\Controllers\Teacher\AttendanceSessionController;
use App\Models\AttendanceSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'auth/login')->name('home');

Route::inertia('dashboard', 'dashboard')->name('dashboard');

Route::get('teacher/class', function (Request $request) {
    $user = $request->user();
    $initialSession = null;

    if ($user && $user->isTeacher()) {
        $now = Carbon::now();
        $sessionId = $request->query('session_id');

        if ($sessionId) {
            $initialSession = AttendanceSession::where('id', $sessionId)
                ->where(function ($q) use ($user): void {
                    $q->where('created_by', $user->id)
                        ->orWhereHas('assignment', fn ($sub) => $sub->where('teacher_id', $user->id));
                })
                ->with(['assignment.subject', 'schoolClass'])
                ->first();
        }

        if (! $initialSession) {
            $initialSession = AttendanceSession::where(function ($q) use ($user): void {
                $q->where('created_by', $user->id)
                    ->orWhereHas('assignment', fn ($sub) => $sub->where('teacher_id', $user->id));
            })
                ->where('start_at', '<=', $now)
                ->where('end_at', '>=', $now)
                ->with(['assignment.subject', 'schoolClass'])
                ->latest('start_at')
                ->first();
        }

        if (! $initialSession) {
            $initialSession = AttendanceSession::where(function ($q) use ($user): void {
                $q->where('created_by', $user->id)
                    ->orWhereHas('assignment', fn ($sub) => $sub->where('teacher_id', $user->id));
            })
                ->with(['assignment.subject', 'schoolClass'])
                ->latest('start_at')
                ->first();
        }
    }

    return Inertia::render('teacher/class-view', [
        'initialSession' => $initialSession,
    ]);
})->name('teacher.class');

Route::inertia('student/scan', 'student/scanner')->name('student.scan');
Route::inertia('student/permission', 'student/permission')->name('student.permission');

Route::middleware(['auth', 'role:TEACHER'])->prefix('teacher')->name('teacher.')->group(function (): void {
    Route::get('attendance-sessions', [AttendanceSessionController::class, 'index'])->name('attendance-sessions.index');
    Route::post('attendance-sessions', [AttendanceSessionController::class, 'store'])->name('attendance-sessions.store');
    Route::get('attendance-sessions/{session}', [AttendanceSessionController::class, 'show'])->name('attendance-sessions.show');
    Route::post('attendance-sessions/{session}/end', [AttendanceSessionController::class, 'end'])->name('attendance-sessions.end');
    Route::get('attendance-sessions/{session}/qr-token', [AttendanceSessionController::class, 'qrToken'])->name('attendance-sessions.qr-token');
    Route::get('attendance-sessions/{session}/monitor', [AttendanceSessionController::class, 'monitor'])->name('attendance-sessions.monitor');
    Route::post('attendance-sessions/{session}/manual-attendance', [AttendanceSessionController::class, 'manualAttendance'])->name('attendance-sessions.manual-attendance');
});

Route::post(
    'student/scan',
    [AttendanceScanController::class, 'store']
)->middleware(['auth', 'role:STUDENT'])->name('student.scan.store');

require __DIR__.'/settings.php';
