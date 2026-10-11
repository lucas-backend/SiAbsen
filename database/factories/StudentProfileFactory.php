<?php

namespace Database\Factories;

use App\Models\EducationLevel;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
{
    protected $model = StudentProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'student_number' => strtoupper(fake()->unique()->bothify('NISN####')),
            'education_level_id' => EducationLevel::factory(),
        ];
    }
}
