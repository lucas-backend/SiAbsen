<?php

namespace Database\Factories;

use App\Models\AttendanceSession;
use App\Models\TeacherAssignment;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<AttendanceSession>
 */
class AttendanceSessionFactory extends Factory
{
    protected $model = AttendanceSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => TeacherAssignment::factory(),
            'class_id' => fn (array $attributes): int => (int) TeacherAssignment::query()
                ->whereKey($attributes['assignment_id'])
                ->value('class_id'),
            'session_date' => now()->toDateString(),
            'start_at' => now(),
            'end_at' => now()->addHours(2),
            'qr_payload' => (string) Str::uuid(),
            'created_by' => User::factory()->teacher(),
            'latitude' => -6.2000000,
            'longitude' => 106.8166660,
        ];
    }

    public function onDate(DateTimeInterface|string $date): static
    {
        return $this->state(function () use ($date): array {
            $start = Carbon::parse($date);

            return [
                'session_date' => $start->toDateString(),
                'start_at' => $start,
                'end_at' => $start->copy()->addHours(2),
            ];
        });
    }
}
