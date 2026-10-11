<?php

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\TeacherAssignment;
use App\Models\User;

test('user role is cast to the UserRole enum', function () {
    $user = User::factory()->create();

    expect($user->role)->toBeInstanceOf(UserRole::class)
        ->and($user->role)->toBe(UserRole::Student)
        ->and(User::factory()->teacher()->create()->role)->toBe(UserRole::Teacher)
        ->and(User::factory()->admin()->create()->role)->toBe(UserRole::Admin);
});

test('attendance record status is cast to the AttendanceStatus enum', function () {
    $record = AttendanceRecord::factory()->create(['status' => AttendanceStatus::Hadir]);

    expect($record->status)->toBeInstanceOf(AttendanceStatus::class)
        ->and($record->status)->toBe(AttendanceStatus::Hadir)
        ->and($record->fresh()->status)->toBe(AttendanceStatus::Hadir)
        ->and(AttendanceRecord::factory()->terlambat()->create()->status)->toBe(AttendanceStatus::Terlambat);
});

test('enum labels are human readable', function () {
    expect(UserRole::Student->label())->toBe('Siswa')
        ->and(UserRole::Teacher->label())->toBe('Guru')
        ->and(UserRole::Admin->label())->toBe('Admin')
        ->and(AttendanceStatus::Hadir->label())->toBe('Hadir')
        ->and(AttendanceStatus::Terlambat->label())->toBe('Terlambat')
        ->and(AttendanceStatus::TidakHadir->label())->toBe('Tidak Hadir')
        ->and(AttendanceStatus::Izin->label())->toBe('Izin')
        ->and(AttendanceStatus::Sakit->label())->toBe('Sakit');
});

test('school class students relation only returns active memberships with pivot data', function () {
    $class = SchoolClass::factory()->create();
    $active = User::factory()->student()->create();
    $inactive = User::factory()->student()->create();

    ClassStudent::factory()->create([
        'class_id' => $class->id,
        'student_id' => $active->id,
        'academic_year' => '2026/2027',
    ]);

    ClassStudent::factory()->inactive()->create([
        'class_id' => $class->id,
        'student_id' => $inactive->id,
        'academic_year' => '2026/2027',
    ]);

    $students = $class->students()->get();

    expect($students)->toHaveCount(1)
        ->and($students->first()->is($active))->toBeTrue()
        ->and($students->first()->pivot->academic_year)->toBe('2026/2027')
        ->and((bool) $students->first()->pivot->is_active)->toBeTrue()
        ->and($class->activeStudents()->count())->toBe(1);
});

test('class student active scope filters out inactive rows', function () {
    ClassStudent::factory()->count(2)->create();
    ClassStudent::factory()->inactive()->create();

    expect(ClassStudent::query()->active()->count())->toBe(2)
        ->and(ClassStudent::query()->count())->toBe(3);
});

test('teacher assignment active scope filters out inactive rows', function () {
    TeacherAssignment::factory()->count(2)->create();
    TeacherAssignment::factory()->inactive()->create();

    expect(TeacherAssignment::query()->active()->count())->toBe(2)
        ->and(TeacherAssignment::query()->count())->toBe(3);
});

test('attendance session on date scope filters sessions by session date', function () {
    $today = AttendanceSession::factory()->onDate(now())->create();
    AttendanceSession::factory()->onDate(now()->subDay())->create();

    $result = AttendanceSession::query()->onDate(now()->toDateString())->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->is($today))->toBeTrue();
});

test('user class students relation returns memberships', function () {
    $student = User::factory()->student()->create();

    ClassStudent::factory()->create([
        'student_id' => $student->id,
        'academic_year' => '2025/2026',
        'is_active' => false,
    ]);
    ClassStudent::factory()->create([
        'student_id' => $student->id,
        'academic_year' => '2026/2027',
    ]);

    expect($student->classStudents)->toHaveCount(2)
        ->and($student->classStudents()->active()->count())->toBe(1);
});
