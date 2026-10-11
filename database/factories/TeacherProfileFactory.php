<?php

namespace Database\Factories;

use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherProfile>
 */
class TeacherProfileFactory extends Factory
{
    protected $model = TeacherProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->teacher(),
        ];
    }
}
