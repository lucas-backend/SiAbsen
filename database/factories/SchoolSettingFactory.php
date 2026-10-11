<?php

namespace Database\Factories;

use App\Models\SchoolSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolSetting>
 */
class SchoolSettingFactory extends Factory
{
    protected $model = SchoolSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'SDN '.fake()->city(),
            'latitude' => -6.2000000,
            'longitude' => 106.8166660,
            'radius_meters' => 50,
        ];
    }
}
