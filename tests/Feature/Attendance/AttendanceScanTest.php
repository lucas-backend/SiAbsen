<?php

use App\Enums\AttendanceStatus;
use App\Enums\LocationStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\TeacherAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * @return array{teacher: User, class: SchoolClass, assignment: TeacherAssignment, student: User, start: CarbonImmutable, session: AttendanceSession}
 */
function attendanceScanFixture(): array
{
    $teacher = User::factory()->teacher()->create();

    $class = SchoolClass::factory()->create([
        'latitude' => -6.2000000,
        'longitude' => 106.8166660,
        'radius_meters' => 50,
    ]);

    $assignment = TeacherAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
    ]);

    $student = User::factory()->student()->create();

    ClassStudent::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
    ]);

    $start = now()->startOfDay()->addHours(8);

    $session = AttendanceSession::factory()->create([
        'assignment_id' => $assignment->id,
        'class_id' => $class->id,
        'created_by' => $teacher->id,
        'start_at' => $start,
        'end_at' => $start->copy()->addHours(2),
        'session_date' => $start->toDateString(),
        'latitude' => $class->latitude,
        'longitude' => $class->longitude,
    ]);

    return compact('teacher', 'class', 'assignment', 'student', 'start', 'session');
}

test('a scan inside the window is recorded as HADIR', function () {
    ['student' => $student, 'start' => $start, 'session' => $session] = attendanceScanFixture();

    $this->travelTo($start->addMinutes(5));

    $this->actingAs($student)
        ->postJson(route('attendance.scan.store', $session), [
            'latitude' => -6.2000000,
            'longitude' => 106.8166660,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', AttendanceStatus::Hadir->value)
        ->assertJsonPath('data.location_status', LocationStatus::Sesuai->value);

    expect(AttendanceRecord::query()->count())->toBe(1);
});

test('a scan after the late tolerance is recorded as TERLAMBAT with late minutes', function () {
    ['student' => $student, 'start' => $start, 'session' => $session] = attendanceScanFixture();

    $this->travelTo($start->addMinutes(20));

    $this->actingAs($student)
        ->postJson(route('attendance.scan.store', $session), [
            'latitude' => -6.2000000,
            'longitude' => 106.8166660,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', AttendanceStatus::Terlambat->value)
        ->assertJsonPath('data.late_minutes', 20);
});

test('a scan before the window opens is rejected', function () {
    ['student' => $student, 'start' => $start, 'session' => $session] = attendanceScanFixture();

    $this->travelTo($start->subMinutes(30));

    $this->actingAs($student)
        ->postJson(route('attendance.scan.store', $session), [
            'latitude' => -6.2000000,
            'longitude' => 106.8166660,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('scan');

    expect(AttendanceRecord::query()->count())->toBe(0);
});

test('a scan after the window closes is rejected', function () {
    ['student' => $student, 'start' => $start, 'session' => $session] = attendanceScanFixture();

    $this->travelTo($start->addHours(3));

    $this->actingAs($student)
        ->postJson(route('attendance.scan.store', $session), [
            'latitude' => -6.2000000,
            'longitude' => 106.8166660,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('scan');

    expect(AttendanceRecord::query()->count())->toBe(0);
});

test('scanning twice creates only one record', function () {
    ['student' => $student, 'start' => $start, 'session' => $session] = attendanceScanFixture();

    $this->travelTo($start->addMinutes(5));

    $payload = [
        'latitude' => -6.2000000,
        'longitude' => 106.8166660,
    ];

    $this->actingAs($student)
        ->postJson(route('attendance.scan.store', $session), $payload)
        ->assertCreated();

    $this->actingAs($student)
        ->postJson(route('attendance.scan.store', $session), $payload)
        ->assertOk();

    expect(AttendanceRecord::query()->count())->toBe(1);
});

test('a far away scan is flagged as DI_LUAR_RADIUS with a plausible distance', function () {
    ['student' => $student, 'start' => $start, 'session' => $session] = attendanceScanFixture();

    $this->travelTo($start->addMinutes(5));

    $response = $this->actingAs($student)
        ->postJson(route('attendance.scan.store', $session), [
            'latitude' => -6.2010000,
            'longitude' => 106.8166660,
        ])
        ->assertCreated()
        ->assertJsonPath('data.location_status', LocationStatus::DiLuarRadius->value);

    expect($response->json('data.distance_meters'))
        ->toBeGreaterThan(90)
        ->toBeLessThan(130);
});

test('a scan without coordinates is flagged as TIDAK_TERVERIFIKASI', function () {
    ['student' => $student, 'start' => $start, 'session' => $session] = attendanceScanFixture();

    $this->travelTo($start->addMinutes(5));

    $this->actingAs($student)
        ->postJson(route('attendance.scan.store', $session))
        ->assertCreated()
        ->assertJsonPath('data.location_status', LocationStatus::TidakTerverifikasi->value)
        ->assertJsonPath('data.distance_meters', null);
});

test('a scan with a mismatched qr payload is rejected', function () {
    ['student' => $student, 'start' => $start, 'session' => $session] = attendanceScanFixture();

    $this->travelTo($start->addMinutes(5));

    $this->actingAs($student)
        ->postJson(route('attendance.scan.store', $session), [
            'qr_payload' => 'not-the-real-payload',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('qr_payload');
});

test('active students who never scanned are reported as TIDAK_HADIR at read time', function () {
    ['teacher' => $teacher, 'class' => $class, 'student' => $student, 'start' => $start, 'session' => $session] = attendanceScanFixture();

    $other = User::factory()->student()->create();

    ClassStudent::factory()->create([
        'class_id' => $class->id,
        'student_id' => $other->id,
    ]);

    $this->travelTo($start->addMinutes(5));

    $this->actingAs($student)
        ->postJson(route('attendance.scan.store', $session), [
            'latitude' => $class->latitude,
            'longitude' => $class->longitude,
        ])
        ->assertCreated();

    $participants = collect(
        $this->actingAs($teacher)
            ->getJson(route('attendance.sessions.show', $session))
            ->assertOk()
            ->json('data.participants')
    )->keyBy('student_id');

    expect($participants[$student->id]['status'])->toBe(AttendanceStatus::Hadir->value)
        ->and($participants[$other->id]['status'])->toBe(AttendanceStatus::TidakHadir->value);

    $this->actingAs($other)
        ->getJson(route('attendance.me'))
        ->assertOk()
        ->assertJsonPath('data.0.status', AttendanceStatus::TidakHadir->value);
});
