<?php

use App\Models\ClassStudent;
use App\Models\EducationLevel;
use App\Models\SchoolClass;
use App\Models\StudentProfile;
use App\Models\TeacherAssignment;
use App\Models\User;

test('academic search lists only the classes a teacher is assigned to for a level', function () {
    $level = EducationLevel::factory()->create(['name' => 'SMP']);
    $teacher = User::factory()->teacher()->create();

    $assigned = SchoolClass::factory()->create([
        'name' => 'Kelas 7A',
        'education_level_id' => $level->id,
    ]);

    TeacherAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'class_id' => $assigned->id,
    ]);

    $unassigned = SchoolClass::factory()->create([
        'name' => 'Kelas 8A',
        'education_level_id' => $level->id,
    ]);

    $names = collect(
        $this->actingAs($teacher)
            ->getJson(route('academic.search', ['education_level_id' => $level->id]))
            ->assertOk()
            ->json('data')
    )->pluck('name');

    expect($names)->toContain('Kelas 7A')
        ->and($names)->not->toContain($unassigned->name);
});

test('academic search lists active students of a class with their student number', function () {
    $teacher = User::factory()->teacher()->create();
    $class = SchoolClass::factory()->create();

    TeacherAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
    ]);

    $student = User::factory()->student()->create(['name' => 'Budi Santoso']);

    StudentProfile::factory()->create([
        'user_id' => $student->id,
        'student_number' => 'NISN000123',
    ]);

    ClassStudent::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
    ]);

    $students = collect(
        $this->actingAs($teacher)
            ->getJson(route('academic.search', ['class_id' => $class->id]))
            ->assertOk()
            ->json('data')
    );

    expect($students)->toHaveCount(1)
        ->and($students->first()['name'])->toBe('Budi Santoso')
        ->and($students->first()['student_number'])->toBe('NISN000123');
});

test('a teacher can not search students of a class they do not teach', function () {
    $teacher = User::factory()->teacher()->create();
    $class = SchoolClass::factory()->create();

    $this->actingAs($teacher)
        ->getJson(route('academic.search', ['class_id' => $class->id]))
        ->assertForbidden();
});

test('a student can not access academic search', function () {
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->getJson(route('academic.search'))
        ->assertForbidden();
});
