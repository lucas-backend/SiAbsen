<?php

use App\Models\AttendanceSession;
use App\Models\TeacherAssignment;
use App\Models\User;

test('a teacher can open a session for their own assignment and the qr payload is unique', function () {
    $teacher = User::factory()->teacher()->create();
    $assignment = TeacherAssignment::factory()->create(['teacher_id' => $teacher->id]);
    $class = $assignment->schoolClass;

    $startAt = now()->addHour();

    $response = $this->actingAs($teacher)->postJson(route('attendance.sessions.store'), [
        'assignment_id' => $assignment->id,
        'start_at' => $startAt->toIso8601String(),
        'end_at' => $startAt->copy()->addHours(2)->toIso8601String(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.assignment_id', $assignment->id)
        ->assertJsonPath('data.class_id', $class->id);

    $session = AttendanceSession::query()->firstOrFail();

    expect($session->qr_payload)->not->toBeEmpty()
        ->and($session->created_by)->toBe($teacher->id)
        ->and($session->session_date->toDateString())->toBe($startAt->toDateString())
        ->and($session->latitude)->toBe($class->latitude);

    $this->actingAs($teacher)->postJson(route('attendance.sessions.store'), [
        'assignment_id' => $assignment->id,
        'start_at' => $startAt->copy()->addDay()->toIso8601String(),
        'end_at' => $startAt->copy()->addDay()->addHours(2)->toIso8601String(),
    ])->assertCreated();

    expect(AttendanceSession::query()->pluck('qr_payload')->unique()->count())->toBe(2);
});

test('a teacher can not open a session for another teachers assignment', function () {
    $owner = User::factory()->teacher()->create();
    $intruder = User::factory()->teacher()->create();
    $assignment = TeacherAssignment::factory()->create(['teacher_id' => $owner->id]);

    $startAt = now()->addHour();

    $this->actingAs($intruder)->postJson(route('attendance.sessions.store'), [
        'assignment_id' => $assignment->id,
        'start_at' => $startAt->toIso8601String(),
        'end_at' => $startAt->copy()->addHours(2)->toIso8601String(),
    ])->assertForbidden();

    expect(AttendanceSession::query()->count())->toBe(0);
});

test('a student can not open an attendance session', function () {
    $student = User::factory()->student()->create();
    $assignment = TeacherAssignment::factory()->create();

    $this->actingAs($student)->postJson(route('attendance.sessions.store'), [
        'assignment_id' => $assignment->id,
        'start_at' => now()->toIso8601String(),
    ])->assertForbidden();
});
