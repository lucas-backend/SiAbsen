<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => AttendanceSession::factory(),
            'student_id' => User::factory()->student(),
            'scanned_at' => now(),
            'status' => AttendanceStatus::Hadir,
            'late_minutes' => 0,
            'latitude' => -6.2000000,
            'longitude' => 106.8166660,
            'distance_meters' => 10,
            'location_status' => 'SESUAI',
        ];
    }

    public function terlambat(int $lateMinutes = 15): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AttendanceStatus::Terlambat,
            'late_minutes' => $lateMinutes,
        ]);
    }

    public function tidakHadir(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AttendanceStatus::TidakHadir,
            'late_minutes' => null,
        ]);
    }
}
