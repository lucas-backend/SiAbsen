<?php

namespace Database\Factories;

use App\Models\EducationLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EducationLevel>
 */
class EducationLevelFactory extends Factory
{
    protected $model = EducationLevel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Jenjang '.fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
