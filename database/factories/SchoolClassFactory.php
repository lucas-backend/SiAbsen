<?php

namespace Database\Factories;

use App\Models\EducationLevel;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Kelas '.fake()->unique()->numberBetween(1, 99999),
            'education_level_id' => EducationLevel::factory(),
            'latitude' => -6.2000000,
            'longitude' => 106.8166660,
            'radius_meters' => 50,
        ];
    }
}
