<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassStudent;
use App\Models\EducationLevel;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. School geofence setting
        SchoolSetting::factory()->create([
            'name' => 'SMP Negeri 1 Jakarta',
            'latitude' => -6.2000000,
            'longitude' => 106.8166660,
            'radius_meters' => 50,
        ]);

        // 2. Education levels
        $levelSd = EducationLevel::factory()->create(['name' => 'SD']);
        $levelSmp = EducationLevel::factory()->create(['name' => 'SMP']);
        $levelSma = EducationLevel::factory()->create(['name' => 'SMA']);

        // 3. Classes
        $classes = [];
        $classNames = [
            $levelSd->id => ['Kelas 1A', 'Kelas 1B', 'Kelas 2A', 'Kelas 3A'],
            $levelSmp->id => ['Kelas 7A', 'Kelas 7B', 'Kelas 8A', 'Kelas 9A'],
            $levelSma->id => ['Kelas 10 MIPA 1', 'Kelas 10 IPS 1', 'Kelas 11 MIPA 1', 'Kelas 12 MIPA 1'],
        ];

        foreach ($classNames as $levelId => $names) {
            foreach ($names as $name) {
                $classes[] = SchoolClass::factory()->create([
                    'name' => $name,
                    'education_level_id' => $levelId,
                ]);
            }
        }

        // 4. Subjects
        $subjectNames = ['Matematika', 'Bahasa Indonesia', 'Bahasa Inggris', 'IPA', 'IPS', 'Pendidikan Agama', 'PJOK', 'Seni Budaya'];
        $subjects = [];
        foreach ($subjectNames as $subjectName) {
            $subjects[] = Subject::factory()->create(['name' => $subjectName]);
        }

        // 5. Admin
        User::factory()->admin()->create([
            'name' => 'Admin Utama',
            'email' => 'admin@siabsen.com',
            'phone' => '081234567890',
        ]);

        // 6. Teachers
        $teacherIds = [];
        for ($i = 1; $i <= 10; $i++) {
            $teacher = User::factory()->teacher()->create([
                'name' => 'Guru '.$i,
                'email' => "guru{$i}@siabsen.com",
                'phone' => '0812345600'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            ]);

            TeacherProfile::factory()->create([
                'user_id' => $teacher->id,
            ]);

            $teacherIds[] = $teacher->id;
        }

        // 7. Students
        $studentIds = [];
        $studentCounter = 1;
        foreach ($classes as $class) {
            for ($s = 1; $s <= 10; $s++) {
                $student = User::factory()->student()->create([
                    'name' => 'Siswa '.$studentCounter,
                    'email' => "siswa{$studentCounter}@siabsen.com",
                    'phone' => '08523456'.str_pad((string) $studentCounter, 4, '0', STR_PAD_LEFT),
                ]);

                StudentProfile::factory()->create([
                    'user_id' => $student->id,
                    'student_number' => 'NISN'.str_pad((string) $studentCounter, 6, '0', STR_PAD_LEFT),
                    'education_level_id' => $class->education_level_id,
                ]);

                ClassStudent::factory()->create([
                    'class_id' => $class->id,
                    'student_id' => $student->id,
                    'academic_year' => '2026/2027',
                ]);

                $studentIds[$class->id][] = $student->id;
                $studentCounter++;
            }
        }

        // 8. Teacher assignments & attendance
        foreach ($classes as $class) {
            $assignedTeachers = collect($teacherIds)->random(2)->all();
            $assignedSubjects = collect($subjects)->random(2)->all();

            foreach ($assignedTeachers as $index => $teacherId) {
                $assignment = TeacherAssignment::factory()->create([
                    'teacher_id' => $teacherId,
                    'subject_id' => $assignedSubjects[$index]->id,
                    'class_id' => $class->id,
                ]);

                for ($sessionNum = 1; $sessionNum <= 5; $sessionNum++) {
                    $sessionDate = now()->subDays($sessionNum * 2);

                    $session = AttendanceSession::factory()->onDate($sessionDate)->create([
                        'assignment_id' => $assignment->id,
                        'class_id' => $class->id,
                        'created_by' => $teacherId,
                    ]);

                    foreach ($studentIds[$class->id] ?? [] as $studentId) {
                        $roll = random_int(1, 10);

                        if ($roll > 8) {
                            // Tidak hadir: not scanned, computed at read time.
                            continue;
                        }

                        $status = $roll > 5
                            ? AttendanceStatus::Terlambat
                            : AttendanceStatus::Hadir;

                        AttendanceRecord::factory()->create([
                            'session_id' => $session->id,
                            'student_id' => $studentId,
                            'scanned_at' => $sessionDate->copy()->addMinutes(random_int(0, 30)),
                            'status' => $status,
                            'late_minutes' => $status === AttendanceStatus::Terlambat
                                ? random_int(10, 30)
                                : 0,
                            'latitude' => -6.2000000 + (random_int(-100, 100) / 1000000),
                            'longitude' => 106.8166660 + (random_int(-100, 100) / 1000000),
                            'distance_meters' => random_int(5, 60),
                            'location_status' => random_int(1, 100) <= 80 ? 'SESUAI' : 'DI_LUAR_RADIUS',
                        ]);
                    }
                }
            }
        }
    }
}
