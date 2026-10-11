<?php

namespace Database\Seeders;

use App\Models\SchoolSetting;
use Illuminate\Database\Seeder;

class SchoolSettingSeeder extends Seeder
{
    public function run(): void
    {
        SchoolSetting::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Gedung Mektan',
                'latitude' => -5.3620750,
                'longitude' => 105.2425030,
                'radius_meters' => 50,
            ]
        );
    }
}
