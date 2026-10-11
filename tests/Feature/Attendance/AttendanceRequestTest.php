<?php

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRequest;
use App\Models\AttendanceSession;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\TeacherAssignment;
use App\Models\User;

/**
 * @return array{teacher: User, class: SchoolClass, assignment: TeacherAssignment, student: User, session: AttendanceSession}
 */
function attendanceRequestFixture(): array
{
    $teacher = User::factory()->teacher()->create();

    $class = SchoolClass::factory()->create();

    $assignment = TeacherAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
    ]);

    $student = User::factory()->student()->create();

    ClassStudent::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'is_active' => true,
    ]);

    $session = AttendanceSession::factory()->create([
        'assignment_id' => $assignment->id,
        'class_id' => $class->id,
        'created_by' => $teacher->id,
    ]);

    return compact('teacher', 'class', 'assignment', 'student', 'session');
}

function makeAttendanceRequest(AttendanceSession $session, User $student, AttendanceStatus $status = AttendanceStatus::Izin): AttendanceRequest
{
    return AttendanceRequest::query()->create([
        'session_id' => $session->getKey(),
        'student_id' => $student->getKey(),
        'requested_status' => $status,
        'reason' => 'Keperluan keluarga',
    ]);
}

test('a student can submit an izin request that is stored as pending', function () {
    ['student' => $student, 'session' => $session] = attendanceRequestFixture();

    $this->actingAs($student)
        ->postJson(route('attendance.requests.store'), [
            'session_id' => $session->id,
            'requested_status' => AttendanceStatus::Izin->value,
            'reason' => 'Acara keluarga',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', AttendanceRequestStatus::Pending->value)
        ->assertJsonPath('data.requested_status', AttendanceStatus::Izin->value);

    $request = AttendanceRequest::query()->firstOrFail();

    expect($request->status)->toBe(AttendanceRequestStatus::Pending)
        ->and($request->student_id)->toBe($student->id)
        ->and($request->reviewed_by)->toBeNull()
        ->and($request->reviewed_at)->toBeNull();
});

test('approving a request records the reviewer and writes an attendance record', function () {
    ['teacher' => $teacher, 'student' => $student, 'session' => $session] = attendanceRequestFixture();

    $request = makeAttendanceRequest($session, $student);

    $this->actingAs($teacher)
        ->patchJson(route('attendance.requests.review', $request), [
            'status' => AttendanceRequestStatus::Approved->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', AttendanceRequestStatus::Approved->value)
        ->assertJsonPath('data.reviewed_by', $teacher->id);

    $request->refresh();

    expect($request->status)->toBe(AttendanceRequestStatus::Approved)
        ->and($request->reviewed_by)->toBe($teacher->id)
        ->and($request->reviewed_at)->not->toBeNull();

    $record = AttendanceRecord::query()
        ->where('session_id', $session->id)
        ->where('student_id', $student->id)
        ->first();

    expect($record)->not->toBeNull()
        ->and($record->status)->toBe(AttendanceStatus::Izin);
});

test('rejecting a request records the reviewer without writing an attendance record', function () {
    ['teacher' => $teacher, 'student' => $student, 'session' => $session] = attendanceRequestFixture();

    $request = makeAttendanceRequest($session, $student, AttendanceStatus::Sakit);

    $this->actingAs($teacher)
        ->patchJson(route('attendance.requests.review', $request), [
            'status' => AttendanceRequestStatus::Rejected->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', AttendanceRequestStatus::Rejected->value);

    $request->refresh();

    expect($request->status)->toBe(AttendanceRequestStatus::Rejected)
        ->and($request->reviewed_by)->toBe($teacher->id)
        ->and($request->reviewed_at)->not->toBeNull()
        ->and(AttendanceRecord::query()->count())->toBe(0);
});

test('a student can not review their own request', function () {
    ['student' => $student, 'session' => $session] = attendanceRequestFixture();

    $request = makeAttendanceRequest($session, $student);

    $this->actingAs($student)
        ->patchJson(route('attendance.requests.review', $request), [
            'status' => AttendanceRequestStatus::Approved->value,
        ])
        ->assertForbidden();

    expect($request->refresh()->status)->toBe(AttendanceRequestStatus::Pending)
        ->and(AttendanceRecord::query()->count())->toBe(0);
});

test('a teacher can not review a request from another teacher session', function () {
    ['student' => $student, 'session' => $session] = attendanceRequestFixture();

    $intruder = User::factory()->teacher()->create();

    $request = makeAttendanceRequest($session, $student);

    $this->actingAs($intruder)
        ->patchJson(route('attendance.requests.review', $request), [
            'status' => AttendanceRequestStatus::Approved->value,
        ])
        ->assertForbidden();

    expect($request->refresh()->status)->toBe(AttendanceRequestStatus::Pending);
});

test('an already reviewed request can not be reviewed again', function () {
    ['teacher' => $teacher, 'student' => $student, 'session' => $session] = attendanceRequestFixture();

    $request = makeAttendanceRequest($session, $student);
    $request->forceFill([
        'status' => AttendanceRequestStatus::Approved,
        'reviewed_by' => $teacher->id,
        'reviewed_at' => now(),
    ])->save();

    $this->actingAs($teacher)
        ->patchJson(route('attendance.requests.review', $request), [
            'status' => AttendanceRequestStatus::Rejected->value,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

test('an admin can review any pending request', function () {
    ['student' => $student, 'session' => $session] = attendanceRequestFixture();

    $admin = User::factory()->admin()->create();
    $request = makeAttendanceRequest($session, $student);

    $this->actingAs($admin)
        ->patchJson(route('attendance.requests.review', $request), [
            'status' => AttendanceRequestStatus::Approved->value,
        ])
        ->assertOk();

    expect($request->refresh()->reviewed_by)->toBe($admin->id);
});

test('the index only exposes requests for the teacher own sessions', function () {
    ['teacher' => $teacher, 'student' => $student, 'session' => $session] = attendanceRequestFixture();

    makeAttendanceRequest($session, $student);

    ['teacher' => $otherTeacher, 'student' => $otherStudent, 'session' => $otherSession] = attendanceRequestFixture();
    $foreign = makeAttendanceRequest($otherSession, $otherStudent);

    $response = $this->actingAs($teacher)
        ->getJson(route('attendance.requests.index'))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.session_id'))->toBe($session->id)
        ->and($response->json('data.0.id'))->not->toBe($foreign->id);
});
