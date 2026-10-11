<?php

use App\Http\Controllers\AcademicSearchController;
use App\Http\Controllers\AttendanceAssignmentController;
use App\Http\Controllers\AttendanceRequestController;
use App\Http\Controllers\AttendanceScanController;
use App\Http\Controllers\AttendanceSessionController;
use App\Http\Controllers\StudentAttendanceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'auth/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::middleware(['role:TEACHER,ADMIN'])->group(function () {
        Route::inertia('teacher/class', 'teacher/class-view')->name('teacher.class');

        Route::get('attendance/sessions', [AttendanceSessionController::class, 'index'])->name('attendance.sessions.index');
        Route::post('attendance/sessions', [AttendanceSessionController::class, 'store'])->name('attendance.sessions.store');
        Route::get('attendance/sessions/{attendanceSession}', [AttendanceSessionController::class, 'show'])->name('attendance.sessions.show');
        Route::get('attendance/assignments', AttendanceAssignmentController::class)->name('attendance.assignments.index');

        Route::get('academic/search', AcademicSearchController::class)->name('academic.search');
    });

    Route::middleware(['role:STUDENT'])->group(function () {
        Route::inertia('student/scan', 'student/scanner')->name('student.scan');
        Route::inertia('student/permission', 'student/permission')->name('student.permission');

        Route::post('attendance/sessions/{attendanceSession}/scan', [AttendanceScanController::class, 'store'])->name('attendance.scan.store');
        Route::get('attendance/me', [StudentAttendanceController::class, 'index'])->name('attendance.me');
        Route::post('attendance/requests', [AttendanceRequestController::class, 'store'])->name('attendance.requests.store');
    });
});

require __DIR__.'/settings.php';
