<?php

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassStudent;
use App\Models\EducationLevel;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;

function setupAttendanceScanEnvironment(): array
{
    // School Setting
    $schoolSetting = SchoolSetting::create([
        'name' => 'SMK Negeri 1 Test',
        'latitude' => -5.3620750,
        'longitude' => 105.2425030,
        'radius_meters' => 50,
    ]);

    // Level, Class, Subject, Teacher
    $level = EducationLevel::create(['name' => 'SMA']);

    $class = SchoolClass::create([
        'name' => '12 RPL 1',
        'education_level_id' => $level->id,
        'latitude' => -5.3620750,
        'longitude' => 105.2425030,
        'radius_meters' => 50,
    ]);

    $teacher = User::factory()->teacher()->create();
    $subject = Subject::create(['name' => 'Pemrograman Web']);

    $assignment = TeacherAssignment::create([
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'is_active' => true,
    ]);

    // Student & Active Membership
    $student = User::factory()->student()->create();
    $membership = ClassStudent::create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'academic_year' => '2025/2026',
        'is_active' => true,
    ]);

    // Active Attendance Session
    $now = Carbon::now();
    $session = AttendanceSession::create([
        'assignment_id' => $assignment->id,
        'class_id' => $class->id,
        'session_date' => $now->toDateString(),
        'start_at' => (clone $now)->subMinutes(5),
        'end_at' => (clone $now)->addMinutes(55),
        'qr_payload' => bin2hex(random_bytes(32)),
        'created_by' => $teacher->id,
        'latitude' => -5.3620750,
        'longitude' => 105.2425030,
    ]);

    return compact('schoolSetting', 'class', 'teacher', 'student', 'session', 'assignment');
}

test('guest cannot access student scan endpoint', function (): void {
    $this->postJson(route('student.scan.store'), [
        'qr_token' => 'invalid-token',
        'latitude' => -5.3620750,
        'longitude' => 105.2425030,
    ])->assertUnauthorized();
});

test('teacher cannot scan attendance', function (): void {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->postJson(route('student.scan.store'), [
            'qr_token' => 'some-token',
            'latitude' => -5.3620750,
            'longitude' => 105.2425030,
        ])
        ->assertForbidden();
});

test('scan request fails when coordinates are missing or invalid', function (): void {
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->postJson(route('student.scan.store'), [
            'qr_token' => 'test-token',
            'latitude' => 105.0, // di luar -90..90
            'longitude' => 105.2425030,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['latitude']);
});

test('student cannot scan non-existent or invalid QR token', function (): void {
    $data = setupAttendanceScanEnvironment();
    $student = $data['student'];

    $this->actingAs($student)
        ->postJson(route('student.scan.store'), [
            'qr_token' => 'non-existent-token-abc',
            'latitude' => -5.3620750,
            'longitude' => 105.2425030,
        ])
        ->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Token QR tidak valid atau sesi absensi tidak ditemukan.',
        ]);
});

test('student cannot scan when session has not started yet', function (): void {
    $data = setupAttendanceScanEnvironment();
    $student = $data['student'];
    /** @var AttendanceSession $session */
    $session = $data['session'];

    $session->update([
        'start_at' => Carbon::now()->addHour(),
        'end_at' => Carbon::now()->addHours(2),
    ]);

    $this->actingAs($student)
        ->postJson(route('student.scan.store'), [
            'qr_token' => $session->qr_payload,
            'latitude' => -5.3620750,
            'longitude' => 105.2425030,
        ])
        ->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Sesi absensi belum dimulai.',
        ]);
});

test('student cannot scan when session has ended or expired', function (): void {
    $data = setupAttendanceScanEnvironment();
    $student = $data['student'];
    /** @var AttendanceSession $session */
    $session = $data['session'];

    $session->update([
        'start_at' => Carbon::now()->subHours(2),
        'end_at' => Carbon::now()->subHour(),
    ]);

    $this->actingAs($student)
        ->postJson(route('student.scan.store'), [
            'qr_token' => $session->qr_payload,
            'latitude' => -5.3620750,
            'longitude' => 105.2425030,
        ])
        ->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Sesi absensi sudah berakhir atau telah ditutup oleh guru.',
        ]);
});

test('student cannot scan if not an active member of the class', function (): void {
    $data = setupAttendanceScanEnvironment();
    $session = $data['session'];

    $otherStudent = User::factory()->student()->create();

    $this->actingAs($otherStudent)
        ->postJson(route('student.scan.store'), [
            'qr_token' => $session->qr_payload,
            'latitude' => -5.3620750,
            'longitude' => 105.2425030,
        ])
        ->assertStatus(403)
        ->assertJsonFragment([
            'message' => 'Anda tidak terdaftar sebagai siswa aktif di kelas ini.',
        ]);
});

test('student can successfully record attendance within geofence radius', function (): void {
    $data = setupAttendanceScanEnvironment();
    $student = $data['student'];
    /** @var AttendanceSession $session */
    $session = $data['session'];

    // Titik lokasi sama persis dengan sekolah (jarak ~0 meter <= 50m)
    $response = $this->actingAs($student)
        ->postJson(route('student.scan.store'), [
            'qr_token' => $session->qr_payload,
            'latitude' => -5.3620750,
            'longitude' => 105.2425030,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'HADIR')
        ->assertJsonPath('data.location_status', 'IN_RANGE');

    $this->assertDatabaseHas('attendance_records', [
        'session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'HADIR',
        'location_status' => 'IN_RANGE',
    ]);
});

test('student can record attendance outside geofence radius as HADIR with OUT_OF_RANGE location status', function (): void {
    $data = setupAttendanceScanEnvironment();
    $student = $data['student'];
    /** @var AttendanceSession $session */
    $session = $data['session'];

    // Titik lokasi berjarak beberapa kilometer (misal Jakarta -6.2, 106.8)
    $response = $this->actingAs($student)
        ->postJson(route('student.scan.store'), [
            'qr_token' => $session->qr_payload,
            'latitude' => -6.2000000,
            'longitude' => 106.8166660,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'HADIR')
        ->assertJsonPath('data.location_status', 'OUT_OF_RANGE');

    $this->assertDatabaseHas('attendance_records', [
        'session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'HADIR',
        'location_status' => 'OUT_OF_RANGE',
    ]);
});

test('duplicate scan by the same student is rejected with 409 Conflict', function (): void {
    $data = setupAttendanceScanEnvironment();
    $student = $data['student'];
    /** @var AttendanceSession $session */
    $session = $data['session'];

    // Scan pertama
    $this->actingAs($student)
        ->postJson(route('student.scan.store'), [
            'qr_token' => $session->qr_payload,
            'latitude' => -5.3620750,
            'longitude' => 105.2425030,
        ])
        ->assertCreated();

    // Scan kedua (duplikat)
    $secondResponse = $this->actingAs($student)
        ->postJson(route('student.scan.store'), [
            'qr_token' => $session->qr_payload,
            'latitude' => -5.3620750,
            'longitude' => 105.2425030,
        ]);

    $secondResponse->assertStatus(409)
        ->assertJsonFragment([
            'message' => 'Anda sudah melakukan absensi pada sesi ini.',
        ]);

    // Memastikan record di database tetap hanya 1
    expect(AttendanceRecord::where('session_id', $session->id)->where('student_id', $student->id)->count())->toBe(1);
});
