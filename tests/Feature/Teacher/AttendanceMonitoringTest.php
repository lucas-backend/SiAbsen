<?php

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassStudent;
use App\Models\EducationLevel;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;

function setupMonitoringTestEnvironment(): array
{
    $level = EducationLevel::create(['name' => 'SMA']);

    $class = SchoolClass::create([
        'name' => '10 MIPA 3',
        'education_level_id' => $level->id,
        'latitude' => -5.3620750,
        'longitude' => 105.2425030,
        'radius_meters' => 50,
    ]);

    $teacher = User::factory()->teacher()->create();
    $subject = Subject::create(['name' => 'Kimia']);

    $assignment = TeacherAssignment::create([
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'is_active' => true,
    ]);

    // Siswa 1: Hadir (dalam radius)
    $student1 = User::factory()->student()->create(['name' => 'Siswa Hadir']);
    ClassStudent::create([
        'class_id' => $class->id,
        'student_id' => $student1->id,
        'is_active' => true,
    ]);

    // Siswa 2: Hadir (luar radius)
    $student2 = User::factory()->student()->create(['name' => 'Siswa Luar Radius']);
    ClassStudent::create([
        'class_id' => $class->id,
        'student_id' => $student2->id,
        'is_active' => true,
    ]);

    // Siswa 3: Belum absen
    $student3 = User::factory()->student()->create(['name' => 'Siswa Belum']);
    ClassStudent::create([
        'class_id' => $class->id,
        'student_id' => $student3->id,
        'is_active' => true,
    ]);

    // Buat sesi
    $now = Carbon::now();
    $session = AttendanceSession::create([
        'assignment_id' => $assignment->id,
        'class_id' => $class->id,
        'session_date' => $now->toDateString(),
        'start_at' => (clone $now)->subMinutes(10),
        'end_at' => (clone $now)->addMinutes(50),
        'qr_payload' => bin2hex(random_bytes(32)),
        'created_by' => $teacher->id,
    ]);

    // Record siswa 1
    AttendanceRecord::create([
        'session_id' => $session->id,
        'student_id' => $student1->id,
        'status' => 'HADIR',
        'scanned_at' => $now,
        'location_status' => 'IN_RANGE',
        'distance_meters' => 15,
        'latitude' => -5.3620750,
        'longitude' => 105.2425030,
    ]);

    // Record siswa 2 (OUT_OF_RANGE)
    AttendanceRecord::create([
        'session_id' => $session->id,
        'student_id' => $student2->id,
        'status' => 'HADIR',
        'scanned_at' => $now,
        'location_status' => 'OUT_OF_RANGE',
        'distance_meters' => 250,
        'latitude' => -5.3600000,
        'longitude' => 105.2400000,
    ]);

    return compact('teacher', 'class', 'assignment', 'session', 'student1', 'student2', 'student3');
}

test('teacher can fetch monitoring data with calculated BELUM_ABSEN and warning for OUT_OF_RANGE', function (): void {
    $data = setupMonitoringTestEnvironment();
    $teacher = $data['teacher'];
    /** @var AttendanceSession $session */
    $session = $data['session'];

    $response = $this->actingAs($teacher)
        ->getJson(route('teacher.attendance-sessions.monitor', $session));

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'session_id',
                'summary' => [
                    'total',
                    'hadir',
                    'belum_hadir',
                    'izin',
                    'sakit',
                    'izin_sakit',
                ],
                'students',
            ],
        ]);

    $summary = $response->json('data.summary');
    expect($summary['total'])->toBe(3)
        ->and($summary['hadir'])->toBe(2)
        ->and($summary['belum_hadir'])->toBe(1);

    $students = collect($response->json('data.students'));

    // Cek siswa 1 (Hadir normal)
    $s1 = $students->firstWhere('id', $data['student1']->id);
    expect($s1['status'])->toBe('HADIR')
        ->and($s1['warn'])->toBeFalse();

    // Cek siswa 2 (Hadir luar radius)
    $s2 = $students->firstWhere('id', $data['student2']->id);
    expect($s2['status'])->toBe('HADIR')
        ->and($s2['warn'])->toBeTrue()
        ->and($s2['location_status'])->toBe('OUT_OF_RANGE');

    // Cek siswa 3 (Belum absen)
    $s3 = $students->firstWhere('id', $data['student3']->id);
    expect($s3['status'])->toBe('BELUM_ABSEN')
        ->and($s3['warn'])->toBeFalse()
        ->and($s3['time'])->toBeNull();
});

test('teacher can mark unrecorded student as SAKIT or IZIN with null scanned_at', function (): void {
    $data = setupMonitoringTestEnvironment();
    $teacher = $data['teacher'];
    /** @var AttendanceSession $session */
    $session = $data['session'];
    $student3 = $data['student3'];

    $response = $this->actingAs($teacher)
        ->postJson(route('teacher.attendance-sessions.manual-attendance', $session), [
            'student_id' => $student3->id,
            'status' => 'IZIN',
        ]);

    $response->assertOk()
        ->assertJsonFragment([
            'status' => 'IZIN',
            'student_id' => $student3->id,
        ]);

    $this->assertDatabaseHas('attendance_records', [
        'session_id' => $session->id,
        'student_id' => $student3->id,
        'status' => 'IZIN',
        'scanned_at' => null,
    ]);
});

test('teacher cannot mark student who already scanned HADIR', function (): void {
    $data = setupMonitoringTestEnvironment();
    $teacher = $data['teacher'];
    /** @var AttendanceSession $session */
    $session = $data['session'];
    $student1 = $data['student1'];

    $this->actingAs($teacher)
        ->postJson(route('teacher.attendance-sessions.manual-attendance', $session), [
            'student_id' => $student1->id,
            'status' => 'SAKIT',
        ])
        ->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Tidak dapat mengubah status siswa yang sudah melakukan scan kehadiran mandiri.',
        ]);
});

test('unauthorized teacher cannot view or edit attendance monitoring', function (): void {
    $data = setupMonitoringTestEnvironment();
    /** @var AttendanceSession $session */
    $session = $data['session'];
    $otherTeacher = User::factory()->teacher()->create();

    $this->actingAs($otherTeacher)
        ->getJson(route('teacher.attendance-sessions.monitor', $session))
        ->assertForbidden();

    $this->actingAs($otherTeacher)
        ->postJson(route('teacher.attendance-sessions.manual-attendance', $session), [
            'student_id' => $data['student3']->id,
            'status' => 'IZIN',
        ])
        ->assertForbidden();
});
