<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\EducationLevel;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Agregasi rekap absensi per kelas, mata pelajaran, periode, dan siswa.
 *
 * `TIDAK_HADIR` tidak pernah dibaca dari tabel: status dihitung saat baca
 * untuk setiap siswa aktif yang belum memiliki record pada sebuah sesi.
 */
class ReportService
{
    /**
     * Rekap lengkap (baris detail + ringkasan) sesuai filter.
     *
     * @param  array<string, mixed>  $filters
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function report(User $user, array $filters): array
    {
        $rows = $this->rows($user, $filters);

        return [
            'rows' => $rows,
            'summary' => $this->summarize($rows),
        ];
    }

    /**
     * Baris detail absensi: satu baris per siswa aktif per sesi, dengan
     * `TIDAK_HADIR` dihitung saat baca.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function rows(User $user, array $filters): array
    {
        $sessions = $this->sessionsQuery($user, $filters)->get();

        if ($sessions->isEmpty()) {
            return [];
        }

        /** @var array<int, Collection<int, User>> $studentsByClass */
        $studentsByClass = [];

        foreach ($sessions->pluck('class_id')->unique()->all() as $classId) {
            $class = SchoolClass::query()->find((int) $classId);

            if ($class instanceof SchoolClass) {
                $studentsByClass[(int) $classId] = $class->activeStudents()
                    ->with('studentProfile')
                    ->get()
                    ->keyBy(fn (User $student): int => (int) $student->getKey());
            }
        }

        $rows = [];

        foreach ($sessions as $session) {
            $records = $session->records->keyBy('student_id');
            $students = $studentsByClass[$session->class_id] ?? collect();

            foreach ($students as $student) {
                if (isset($filters['student_id']) && (int) $filters['student_id'] !== (int) $student->getKey()) {
                    continue;
                }

                $record = $records->get($student->getKey());
                $status = $record instanceof AttendanceRecord ? $record->status : AttendanceStatus::TidakHadir;

                if (isset($filters['status']) && $status->value !== $filters['status']) {
                    continue;
                }

                $rows[] = $this->transformRow($session, $student, $record, $status);
            }
        }

        return $rows;
    }

    /**
     * Ringkasan agregat yang konsisten dengan baris detail.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int|float>
     */
    public function summarize(array $rows): array
    {
        $summary = [
            'hadir' => 0,
            'terlambat' => 0,
            'tidak_hadir' => 0,
            'izin' => 0,
            'sakit' => 0,
        ];

        foreach ($rows as $row) {
            match ($row['status'] ?? null) {
                AttendanceStatus::Hadir->value => $summary['hadir']++,
                AttendanceStatus::Terlambat->value => $summary['terlambat']++,
                AttendanceStatus::TidakHadir->value => $summary['tidak_hadir']++,
                AttendanceStatus::Izin->value => $summary['izin']++,
                AttendanceStatus::Sakit->value => $summary['sakit']++,
                default => null,
            };
        }

        $total = count($rows);
        $kehadiran = $summary['hadir'] + $summary['terlambat'];

        return [
            ...$summary,
            'total' => $total,
            'kehadiran' => $kehadiran,
            'persentase_kehadiran' => $total > 0
                ? round($kehadiran / $total * 100, 1)
                : 0.0,
        ];
    }

    /**
     * Daftar jenjang pendidikan untuk filter tingkat pertama.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function educationLevels(): array
    {
        return EducationLevel::query()
            ->orderBy('name')
            ->get()
            ->map(fn (EducationLevel $level): array => [
                'id' => (int) $level->getKey(),
                'name' => $level->name,
            ])
            ->all();
    }

    /**
     * Query sesi absensi yang sudah dibatasi oleh hak akses dan filter bertingkat.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<AttendanceSession>
     */
    public function sessionsQuery(User $user, array $filters): Builder
    {
        return AttendanceSession::query()
            ->with(['schoolClass', 'assignment.subject', 'records'])
            ->when(
                ! $user->isAdmin(),
                fn (Builder $query) => $query->whereHas(
                    'assignment',
                    fn (Builder $assignment) => $assignment->where('teacher_id', $user->getKey()),
                ),
            )
            ->when(
                isset($filters['education_level_id']),
                fn (Builder $query) => $query->whereHas(
                    'schoolClass',
                    fn (Builder $class) => $class->where('education_level_id', (int) $filters['education_level_id']),
                ),
            )
            ->when(
                isset($filters['class_id']),
                fn (Builder $query) => $query->where('class_id', (int) $filters['class_id']),
            )
            ->when(
                isset($filters['subject_id']),
                fn (Builder $query) => $query->whereHas(
                    'assignment',
                    fn (Builder $assignment) => $assignment->where('subject_id', (int) $filters['subject_id']),
                ),
            )
            ->when(
                isset($filters['date_from']),
                fn (Builder $query) => $query->whereDate('session_date', '>=', $filters['date_from']),
            )
            ->when(
                isset($filters['date_to']),
                fn (Builder $query) => $query->whereDate('session_date', '<=', $filters['date_to']),
            )
            ->orderBy('session_date')
            ->orderBy('start_at');
    }

    /**
     * @return array<string, mixed>
     */
    private function transformRow(
        AttendanceSession $session,
        User $student,
        ?AttendanceRecord $record,
        AttendanceStatus $status,
    ): array {
        $class = $session->schoolClass;
        $assignment = $session->assignment;
        $locationStatus = $record?->location_status;

        return [
            'session_id' => (int) $session->getKey(),
            'session_date' => $session->session_date->toDateString(),
            'start_at' => $session->start_at->toIso8601String(),
            'class_id' => $session->class_id,
            'class_name' => $class?->name,
            'subject_id' => $assignment?->subject_id,
            'subject_name' => $assignment?->subject?->name,
            'student_id' => (int) $student->getKey(),
            'student_name' => $student->name,
            'student_number' => $student->studentProfile?->student_number,
            'status' => $status->value,
            'status_label' => $status->label(),
            'late_minutes' => $record?->late_minutes,
            'scanned_at' => $record?->scanned_at?->toIso8601String(),
            'location_status' => $locationStatus?->value,
            'location_label' => $locationStatus?->label(),
            'distance_meters' => $record?->distance_meters,
            'latitude' => $record?->latitude,
            'longitude' => $record?->longitude,
        ];
    }
}
