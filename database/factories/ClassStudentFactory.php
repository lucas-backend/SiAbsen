<?php

namespace Database\Factories;

use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassStudent>
 */
class ClassStudentFactory extends Factory
{
    protected $model = ClassStudent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'student_id' => User::factory()->student(),
            'academic_year' => '2026/2027',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
