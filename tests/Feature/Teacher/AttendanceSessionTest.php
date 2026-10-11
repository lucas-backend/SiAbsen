<?php

use App\Models\AttendanceSession;
use App\Models\EducationLevel;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;

function createTestTeacherAndAssignment(): array
{
    $teacher = User::factory()->teacher()->create();

    $level = EducationLevel::create(['name' => 'SMA']);

    $class = SchoolClass::create([
        'name' => '10 MIPA 1',
        'education_level_id' => $level->id,
        'latitude' => -6.2000000,
        'longitude' => 106.8166660,
        'radius_meters' => 50,
    ]);

    $subject = Subject::create(['name' => 'Fisika']);

    $assignment = TeacherAssignment::create([
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'is_active' => true,
    ]);

    return compact('teacher', 'class', 'subject', 'assignment');
}

test('guest cannot access teacher attendance session endpoints', function (): void {
    $this->getJson(route('teacher.attendance-sessions.index'))
        ->assertUnauthorized();

    $this->postJson(route('teacher.attendance-sessions.store'), [])
        ->assertUnauthorized();
});

test('student cannot create attendance sessions', function (): void {
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->postJson(route('teacher.attendance-sessions.store'), [
            'assignment_id' => 1,
            'class_id' => 1,
            'start_at' => now()->toDateTimeString(),
            'end_at' => now()->addHour()->toDateTimeString(),
        ])
        ->assertForbidden();
});

test('teacher can create an attendance session with valid inputs and active assignment', function (): void {
    $data = createTestTeacherAndAssignment();
    $teacher = $data['teacher'];
    $assignment = $data['assignment'];
    $class = $data['class'];

    $startAt = Carbon::now()->addMinutes(10);
    $endAt = (clone $startAt)->addMinutes(90);

    $response = $this->actingAs($teacher)
        ->postJson(route('teacher.attendance-sessions.store'), [
            'assignment_id' => $assignment->id,
            'class_id' => $class->id,
            'session_date' => $startAt->toDateString(),
            'start_at' => $startAt->toDateTimeString(),
            'end_at' => $endAt->toDateTimeString(),
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'assignment_id',
                'class_id',
                'session_date',
                'start_at',
                'end_at',
                'qr_payload',
                'created_by',
            ],
        ]);

    $payload = $response->json('data.qr_payload');
    expect(strlen($payload))->toBe(64)
        ->and($payload)->not->toContain((string) $teacher->id)
        ->and($response->json('data.created_by'))->toBe($teacher->id);

    $this->assertDatabaseHas('attendance_sessions', [
        'id' => $response->json('data.id'),
        'assignment_id' => $assignment->id,
        'class_id' => $class->id,
        'qr_payload' => $payload,
    ]);
});

test('teacher cannot create session if assignment belongs to another teacher', function (): void {
    $data = createTestTeacherAndAssignment();
    $assignment = $data['assignment'];
    $class = $data['class'];

    $otherTeacher = User::factory()->teacher()->create();

    $startAt = Carbon::now()->addMinutes(10);
    $endAt = (clone $startAt)->addMinutes(90);

    $response = $this->actingAs($otherTeacher)
        ->postJson(route('teacher.attendance-sessions.store'), [
            'assignment_id' => $assignment->id,
            'class_id' => $class->id,
            'start_at' => $startAt->toDateTimeString(),
            'end_at' => $endAt->toDateTimeString(),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['assignment_id']);
});

test('teacher cannot create session if class_id does not match assignment class_id', function (): void {
    $data = createTestTeacherAndAssignment();
    $teacher = $data['teacher'];
    $assignment = $data['assignment'];

    $level = EducationLevel::first();
    $differentClass = SchoolClass::create([
        'name' => '10 MIPA 2',
        'education_level_id' => $level->id,
    ]);

    $startAt = Carbon::now()->addMinutes(10);
    $endAt = (clone $startAt)->addMinutes(90);

    $response = $this->actingAs($teacher)
        ->postJson(route('teacher.attendance-sessions.store'), [
            'assignment_id' => $assignment->id,
            'class_id' => $differentClass->id,
            'start_at' => $startAt->toDateTimeString(),
            'end_at' => $endAt->toDateTimeString(),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['class_id']);
});

test('validation rejects when end_at is before or equal to start_at', function (): void {
    $data = createTestTeacherAndAssignment();
    $teacher = $data['teacher'];
    $assignment = $data['assignment'];
    $class = $data['class'];

    $startAt = Carbon::now()->addHour();
    $invalidEndAt = Carbon::now()->addMinutes(30);

    $response = $this->actingAs($teacher)
        ->postJson(route('teacher.attendance-sessions.store'), [
            'assignment_id' => $assignment->id,
            'class_id' => $class->id,
            'start_at' => $startAt->toDateTimeString(),
            'end_at' => $invalidEndAt->toDateTimeString(),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['end_at']);
});

test('duplicate session with exact same assignment, date, start_at, and end_at is rejected', function (): void {
    $data = createTestTeacherAndAssignment();
    $teacher = $data['teacher'];
    $assignment = $data['assignment'];
    $class = $data['class'];

    $startAt = Carbon::now()->addMinutes(10);
    $endAt = (clone $startAt)->addMinutes(90);

    $payload = [
        'assignment_id' => $assignment->id,
        'class_id' => $class->id,
        'session_date' => $startAt->toDateString(),
        'start_at' => $startAt->toDateTimeString(),
        'end_at' => $endAt->toDateTimeString(),
    ];

    $first = $this->actingAs($teacher)->postJson(route('teacher.attendance-sessions.store'), $payload);
    $first->assertCreated();

    $second = $this->actingAs($teacher)->postJson(route('teacher.attendance-sessions.store'), $payload);
    $second->assertStatus(422)
        ->assertJsonValidationErrors(['session']);
});

test('teacher can end an active attendance session', function (): void {
    $data = createTestTeacherAndAssignment();
    $teacher = $data['teacher'];
    $assignment = $data['assignment'];
    $class = $data['class'];

    $startAt = Carbon::now()->subMinutes(30);
    $endAt = Carbon::now()->addMinutes(60);

    $session = AttendanceSession::create([
        'assignment_id' => $assignment->id,
        'class_id' => $class->id,
        'session_date' => $startAt->toDateString(),
        'start_at' => $startAt,
        'end_at' => $endAt,
        'qr_payload' => bin2hex(random_bytes(32)),
        'created_by' => $teacher->id,
    ]);

    expect($session->isExpired())->toBeFalse();

    $response = $this->actingAs($teacher)
        ->postJson(route('teacher.attendance-sessions.end', $session));

    $response->assertOk()
        ->assertJsonFragment(['message' => 'Sesi absensi berhasil diakhiri.']);

    $session->refresh();
    expect($session->end_at->isPast())->toBeTrue();
});

test('teacher cannot end session belonging to another teacher', function (): void {
    $data = createTestTeacherAndAssignment();
    $teacher = $data['teacher'];
    $assignment = $data['assignment'];
    $class = $data['class'];

    $otherTeacher = User::factory()->teacher()->create();

    $startAt = Carbon::now()->subMinutes(30);
    $endAt = Carbon::now()->addMinutes(60);

    $session = AttendanceSession::create([
        'assignment_id' => $assignment->id,
        'class_id' => $class->id,
        'session_date' => $startAt->toDateString(),
        'start_at' => $startAt,
        'end_at' => $endAt,
        'qr_payload' => bin2hex(random_bytes(32)),
        'created_by' => $teacher->id,
    ]);

    $response = $this->actingAs($otherTeacher)
        ->postJson(route('teacher.attendance-sessions.end', $session));

    $response->assertForbidden();
});
