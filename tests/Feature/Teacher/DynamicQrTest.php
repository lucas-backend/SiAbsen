<?php

use App\Models\AttendanceSession;
use App\Models\ClassStudent;
use App\Models\EducationLevel;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\DynamicQrService;
use Illuminate\Support\Carbon;

function setupDynamicQrTestEnvironment(): array
{
    $schoolSetting = SchoolSetting::create([
        'name' => 'SMK Dynamic QR Test',
        'latitude' => -5.3620750,
        'longitude' => 105.2425030,
        'radius_meters' => 50,
    ]);

    $level = EducationLevel::create(['name' => 'SMA']);

    $class = SchoolClass::create([
        'name' => '11 IPA 2',
        'education_level_id' => $level->id,
        'latitude' => -5.3620750,
        'longitude' => 105.2425030,
        'radius_meters' => 50,
    ]);

    $teacher = User::factory()->teacher()->create();
    $subject = Subject::create(['name' => 'Biologi']);

    $assignment = TeacherAssignment::create([
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'is_active' => true,
    ]);

    $student = User::factory()->student()->create();
    ClassStudent::create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'academic_year' => '2025/2026',
        'is_active' => true,
    ]);

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

test('teacher can fetch dynamic QR token for own active session', function (): void {
    $data = setupDynamicQrTestEnvironment();
    $teacher = $data['teacher'];
    /** @var AttendanceSession $session */
    $session = $data['session'];

    $response = $this->actingAs($teacher)
        ->getJson(route('teacher.attendance-sessions.qr-token', $session));

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'session_id',
                'qr_token',
                'expires_at',
                'expires_in',
                'is_active',
            ],
        ]);

    $token = $response->json('data.qr_token');
    expect(str_contains($token, '.'))->toBeTrue()
        ->and($response->json('data.session_id'))->toBe($session->id)
        ->and($response->json('data.expires_in'))->toBe(30);
});

test('another teacher cannot fetch dynamic QR token for session they do not own', function (): void {
    $data = setupDynamicQrTestEnvironment();
    /** @var AttendanceSession $session */
    $session = $data['session'];

    $otherTeacher = User::factory()->teacher()->create();

    $this->actingAs($otherTeacher)
        ->getJson(route('teacher.attendance-sessions.qr-token', $session))
        ->assertForbidden();
});

test('teacher cannot fetch dynamic QR token when session has ended', function (): void {
    $data = setupDynamicQrTestEnvironment();
    $teacher = $data['teacher'];
    /** @var AttendanceSession $session */
    $session = $data['session'];

    $session->update([
        'end_at' => Carbon::now()->subMinutes(1),
    ]);

    $this->actingAs($teacher)
        ->getJson(route('teacher.attendance-sessions.qr-token', $session))
        ->assertStatus(422)
        ->assertJsonPath('is_active', false);
});

test('student scan succeeds using valid dynamic QR token', function (): void {
    $data = setupDynamicQrTestEnvironment();
    $student = $data['student'];
    /** @var AttendanceSession $session */
    $session = $data['session'];

    /** @var DynamicQrService $dynamicQrService */
    $dynamicQrService = app(DynamicQrService::class);
    $tokenData = $dynamicQrService->generateToken($session);

    $response = $this->actingAs($student)
        ->postJson(route('student.scan.store'), [
            'qr_token' => $tokenData['token'],
            'latitude' => -5.3620750,
            'longitude' => 105.2425030,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'HADIR')
        ->assertJsonPath('data.location_status', 'IN_RANGE');
});

test('student scan rejects expired dynamic QR token', function (): void {
    $data = setupDynamicQrTestEnvironment();
    $student = $data['student'];
    /** @var AttendanceSession $session */
    $session = $data['session'];

    // Buat token yang sudah kedaluwarsa 40 detik yang lalu (melebihi 30s + 5s grace period)
    $expiredTimestamp = Carbon::now()->timestamp - 40;
    $appKey = (string) config('app.key');
    $sig = hash_hmac('sha256', "{$session->id}:{$expiredTimestamp}", (string) $session->qr_payload.$appKey);
    $expiredToken = "{$session->id}.{$expiredTimestamp}.{$sig}";

    $response = $this->actingAs($student)
        ->postJson(route('student.scan.store'), [
            'qr_token' => $expiredToken,
            'latitude' => -5.3620750,
            'longitude' => 105.2425030,
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Token QR sudah kedaluwarsa. Silakan scan QR terbaru.',
        ]);
});

test('student scan rejects tampered dynamic QR token with invalid signature', function (): void {
    $data = setupDynamicQrTestEnvironment();
    $student = $data['student'];
    /** @var AttendanceSession $session */
    $session = $data['session'];

    $futureTimestamp = Carbon::now()->timestamp + 25;
    $tamperedToken = "{$session->id}.{$futureTimestamp}.invalid-signature-1234567890abcdef";

    $response = $this->actingAs($student)
        ->postJson(route('student.scan.store'), [
            'qr_token' => $tamperedToken,
            'latitude' => -5.3620750,
            'longitude' => 105.2425030,
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Tanda tangan digital token QR tidak valid.',
        ]);
});
