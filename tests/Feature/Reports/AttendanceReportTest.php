<?php

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassStudent;
use App\Models\EducationLevel;
use App\Models\SchoolClass;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{
 *     level: EducationLevel,
 *     class: SchoolClass,
 *     subject: Subject,
 *     teacher: User,
 *     assignment: TeacherAssignment,
 *     students: Collection<int, User>,
 *     session: AttendanceSession
 * }
 */
function reportScenario(string $className = 'Kelas 7A'): array
{
    $level = EducationLevel::factory()->create(['name' => 'SMP '.$className]);
    $class = SchoolClass::factory()->create([
        'name' => $className,
        'education_level_id' => $level->id,
    ]);
    $subject = Subject::factory()->create(['name' => 'Matematika '.$className]);
    $teacher = User::factory()->teacher()->create();
    $assignment = TeacherAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
    ]);

    $students = collect([1, 2, 3])->map(
        function (int $index) use ($class, $level, $className): User {
            $student = User::factory()->student()->create([
                'name' => "Siswa {$index} {$className}",
            ]);

            StudentProfile::factory()->create([
                'user_id' => $student->id,
                'student_number' => 'NISN-'.str_replace(' ', '', $className)."-{$index}",
                'education_level_id' => $level->id,
            ]);

            ClassStudent::factory()->create([
                'class_id' => $class->id,
                'student_id' => $student->id,
            ]);

            return $student;
        },
    );

    $session = AttendanceSession::factory()->create([
        'assignment_id' => $assignment->id,
        'class_id' => $class->id,
        'created_by' => $teacher->id,
        'session_date' => '2026-02-10',
        'start_at' => '2026-02-10 07:00:00',
        'end_at' => '2026-02-10 09:00:00',
    ]);

    AttendanceRecord::factory()->create([
        'session_id' => $session->id,
        'student_id' => $students[0]->id,
        'status' => AttendanceStatus::Hadir,
    ]);

    AttendanceRecord::factory()->terlambat(12)->create([
        'session_id' => $session->id,
        'student_id' => $students[1]->id,
    ]);

    return compact('level', 'class', 'subject', 'teacher', 'assignment', 'students', 'session');
}

test('a teacher only sees sessions of classes they teach', function () {
    $scenario = reportScenario();

    $other = reportScenario('Kelas 8A');

    $response = $this->actingAs($scenario['teacher'])
        ->getJson(route('reports.data'))
        ->assertOk();

    $rows = collect($response->json('data.rows'));

    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('class_id')->unique()->all())->toBe([
            $scenario['class']->id,
        ])
        ->and($rows->pluck('class_id'))->not->toContain($other['class']->id);
});

test('summary stays consistent with the detail rows and computes tidak hadir on the fly', function () {
    $scenario = reportScenario();

    $summary = $this->actingAs($scenario['teacher'])
        ->getJson(route('reports.data'))
        ->assertOk()
        ->json('data.summary');

    expect($summary)->toMatchArray([
        'hadir' => 1,
        'terlambat' => 1,
        'tidak_hadir' => 1,
        'izin' => 0,
        'sakit' => 0,
        'total' => 3,
        'kehadiran' => 2,
    ])
        ->and($summary['persentase_kehadiran'])->toBe(66.7);
});

test('nested filters narrow the report by class, subject, student, date, and status', function () {
    $scenario = reportScenario();
    $teacher = $scenario['teacher'];

    $byClass = collect(
        $this->actingAs($teacher)
            ->getJson(route('reports.data', ['class_id' => $scenario['class']->id]))
            ->assertOk()
            ->json('data.rows')
    );

    $bySubject = collect(
        $this->actingAs($teacher)
            ->getJson(route('reports.data', ['subject_id' => $scenario['subject']->id]))
            ->assertOk()
            ->json('data.rows')
    );

    $byStudent = collect(
        $this->actingAs($teacher)
            ->getJson(route('reports.data', ['student_id' => $scenario['students'][2]->id]))
            ->assertOk()
            ->json('data.rows')
    );

    $byStatus = collect(
        $this->actingAs($teacher)
            ->getJson(route('reports.data', ['status' => AttendanceStatus::TidakHadir->value]))
            ->assertOk()
            ->json('data.rows')
    );

    $outsidePeriod = collect(
        $this->actingAs($teacher)
            ->getJson(route('reports.data', ['date_from' => '2026-03-01']))
            ->assertOk()
            ->json('data.rows')
    );

    expect($byClass)->toHaveCount(3)
        ->and($bySubject)->toHaveCount(3)
        ->and($byStudent)->toHaveCount(1)
        ->and($byStudent->first()['student_id'])->toBe($scenario['students'][2]->id)
        ->and($byStudent->first()['status'])->toBe(AttendanceStatus::TidakHadir->value)
        ->and($byStatus)->toHaveCount(1)
        ->and($byStatus->first()['student_id'])->toBe($scenario['students'][2]->id)
        ->and($outsidePeriod)->toBeEmpty();
});

test('filtering by a class the teacher does not teach is forbidden', function () {
    $scenario = reportScenario();
    $foreign = reportScenario('Kelas 9C');

    $this->actingAs($scenario['teacher'])
        ->getJson(route('reports.data', ['class_id' => $foreign['class']->id]))
        ->assertForbidden();
});

test('an admin can view reports across every class', function () {
    $scenario = reportScenario();
    $other = reportScenario('Kelas 8A');
    $admin = User::factory()->admin()->create();

    $rows = collect(
        $this->actingAs($admin)
            ->getJson(route('reports.data'))
            ->assertOk()
            ->json('data.rows')
    );

    $classIds = $rows->pluck('class_id')->unique()->values()->all();

    expect($classIds)->toContain($scenario['class']->id)
        ->and($classIds)->toContain($other['class']->id);
});

test('exporting the report streams a csv containing the filtered rows', function () {
    $scenario = reportScenario();

    $response = $this->actingAs($scenario['teacher'])
        ->get(route('reports.export', ['class_id' => $scenario['class']->id]))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('Tanggal')
        ->and($csv)->toContain('Siswa 1')
        ->and($csv)->toContain('Tidak Hadir');
});

test('students may not access the reports', function () {
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->getJson(route('reports.data'))
        ->assertForbidden();

    $this->actingAs($student)
        ->get(route('reports.index'))
        ->assertForbidden();
});

test('the report and nested search pages render with their initial props', function () {
    $scenario = reportScenario();

    $this->actingAs($scenario['teacher'])
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/index')
            ->has('educationLevels')
            ->has('statuses', 5)
            ->has('filters'));

    $this->actingAs($scenario['teacher'])
        ->get(route('reports.search'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/search')
            ->has('educationLevels'));
});
