<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $password = Hash::make('password');

        // 1. Education Levels
        $levelSd = DB::table('education_levels')->insertGetId(['name' => 'SD', 'created_at' => $now, 'updated_at' => $now]);
        $levelSmp = DB::table('education_levels')->insertGetId(['name' => 'SMP', 'created_at' => $now, 'updated_at' => $now]);
        $levelSma = DB::table('education_levels')->insertGetId(['name' => 'SMA', 'created_at' => $now, 'updated_at' => $now]);

        // 2. Classes
        $classes = [];
        $classNames = [
            $levelSd => ['Kelas 1A', 'Kelas 1B', 'Kelas 2A', 'Kelas 3A'],
            $levelSmp => ['Kelas 7A', 'Kelas 7B', 'Kelas 8A', 'Kelas 9A'],
            $levelSma => ['Kelas 10 MIPA 1', 'Kelas 10 IPS 1', 'Kelas 11 MIPA 1', 'Kelas 12 MIPA 1'],
        ];

        foreach ($classNames as $levelId => $names) {
            foreach ($names as $name) {
                $classes[] = DB::table('classes')->insertGetId([
                    'name' => $name,
                    'education_level_id' => $levelId,
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
            }
        }

        // 3. Subjects
        $subjects = [];
        $subjectNames = ['Matematika', 'Bahasa Indonesia', 'Bahasa Inggris', 'IPA', 'IPS', 'Pendidikan Agama', 'PJOK', 'Seni Budaya'];
        foreach ($subjectNames as $subjectName) {
            $subjects[] = DB::table('subjects')->insertGetId(['name' => $subjectName, 'created_at' => $now, 'updated_at' => $now]);
        }

        // 4. Admin
        DB::table('users')->insert([
            'name' => 'Admin Utama',
            'email' => 'admin@siabsen.com',
            'password' => $password,
            'role' => 'ADMIN',
            'phone' => '081234567890',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 5. Teachers
        $teacherIds = [];
        for ($i = 1; $i <= 10; $i++) {
            $teacherId = DB::table('users')->insertGetId([
                'name' => "Guru " . $i,
                'email' => "guru{$i}@siabsen.com",
                'password' => $password,
                'role' => 'TEACHER',
                'phone' => "0812345600" . str_pad($i, 2, '0', STR_PAD_LEFT),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            
            DB::table('teacher_profiles')->insert([
                'user_id' => $teacherId,
            ]);
            $teacherIds[] = $teacherId;
        }

        // 6. Students
        $studentIds = [];
        $studentCounter = 1;
        foreach ($classes as $classId) {
            // 10 students per class
            for ($s = 1; $s <= 10; $s++) {
                $studentId = DB::table('users')->insertGetId([
                    'name' => "Siswa " . $studentCounter,
                    'email' => "siswa{$studentCounter}@siabsen.com",
                    'password' => $password,
                    'role' => 'STUDENT',
                    'phone' => "08523456" . str_pad($studentCounter, 4, '0', STR_PAD_LEFT),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // We get the actual education_level_id for this class
                $classInfo = DB::table('classes')->where('id', $classId)->first();

                DB::table('student_profiles')->insert([
                    'user_id' => $studentId,
                    'student_number' => 'NISN' . str_pad($studentCounter, 6, '0', STR_PAD_LEFT),
                    'education_level_id' => $classInfo->education_level_id, 
                ]);

                DB::table('class_students')->insert([
                    'class_id' => $classId,
                    'student_id' => $studentId,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $studentIds[$classId][] = $studentId;
                $studentCounter++;
            }
        }

        // 7. Teacher Assignments & Attendance
        foreach ($classes as $classId) {
            // Assign 2 random teachers per class
            $assignedTeachers = array_rand(array_flip($teacherIds), 2);
            // Assign 2 random subjects
            $assignedSubjects = array_rand(array_flip($subjects), 2);

            foreach ($assignedTeachers as $index => $teacherId) {
                $assignmentId = DB::table('teacher_assignments')->insertGetId([
                    'teacher_id' => $teacherId,
                    'subject_id' => $assignedSubjects[$index],
                    'class_id' => $classId,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Create 5 attendance sessions per assignment
                for ($sessionNum = 1; $sessionNum <= 5; $sessionNum++) {
                    $sessionDate = clone $now;
                    $sessionDate->subDays(rand(1, 30));

                    $sessionId = DB::table('attendance_sessions')->insertGetId([
                        'assignment_id' => $assignmentId,
                        'class_id' => $classId,
                        'session_date' => $sessionDate->toDateString(),
                        'start_at' => $sessionDate,
                        'end_at' => (clone $sessionDate)->addHours(2),
                        'qr_payload' => Str::random(40),
                        'created_by' => $teacherId,
                        'created_at' => $sessionDate,
                        'updated_at' => $sessionDate,
                    ]);

                    // Add attendance records for all students in this class
                    if (isset($studentIds[$classId])) {
                        foreach ($studentIds[$classId] as $studentId) {
                            $statuses = ['HADIR', 'HADIR', 'HADIR', 'HADIR', 'TERLAMBAT', 'TIDAK_HADIR'];
                            $status = $statuses[array_rand($statuses)];
                            
                            DB::table('attendance_records')->insert([
                                'session_id' => $sessionId,
                                'student_id' => $studentId,
                                'scanned_at' => $status === 'TIDAK_HADIR' ? null : clone $sessionDate->addMinutes(rand(0, 30)),
                                'status' => $status,
                                'late_minutes' => $status === 'TERLAMBAT' ? rand(10, 30) : 0,
                                'created_at' => $sessionDate,
                                'updated_at' => $sessionDate,
                            ]);
                        }
                    }
                }
            }
        }
    }
}
