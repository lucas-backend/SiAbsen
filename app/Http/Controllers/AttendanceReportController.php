<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
    ) {}

    /**
     * Halaman rekap absensi dengan filter bertingkat.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', 'attendance-report');

        return Inertia::render('reports/index', [
            'educationLevels' => $this->reports->educationLevels(),
            'statuses' => $this->statusOptions(),
            'filters' => $this->filtersFromRequest($request),
        ]);
    }

    /**
     * Halaman pencarian akademik bertingkat (jenjang → kelas → siswa).
     */
    public function search(): Response
    {
        Gate::authorize('viewAny', 'attendance-report');

        return Inertia::render('reports/search', [
            'educationLevels' => $this->reports->educationLevels(),
        ]);
    }

    /**
     * Data rekap (baris + ringkasan) sesuai filter.
     */
    public function data(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', 'attendance-report');

        $user = $this->currentUser($request);
        $filters = $this->validatedFilters($request, $user);

        return response()->json(['data' => $this->reports->report($user, $filters)]);
    }

    /**
     * Ekspor rekap sesuai filter sebagai berkas CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', 'attendance-report');

        $user = $this->currentUser($request);
        $filters = $this->validatedFilters($request, $user);
        $report = $this->reports->report($user, $filters);

        $filename = 'rekap-absensi-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Tanggal',
                'Kelas',
                'Mata Pelajaran',
                'NIS',
                'Nama Siswa',
                'Status',
                'Terlambat (menit)',
                'Waktu Absen',
                'Status Lokasi',
                'Jarak (m)',
                'Latitude',
                'Longitude',
            ]);

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $this->csvValue($row['session_date'] ?? null),
                    $this->csvValue($row['class_name'] ?? null),
                    $this->csvValue($row['subject_name'] ?? null),
                    $this->csvValue($row['student_number'] ?? null),
                    $this->csvValue($row['student_name'] ?? null),
                    $this->csvValue($row['status_label'] ?? null),
                    $this->csvValue($row['late_minutes'] ?? null),
                    $this->csvValue($row['scanned_at'] ?? null),
                    $this->csvValue($row['location_label'] ?? null),
                    $this->csvValue($row['distance_meters'] ?? null),
                    $this->csvValue($row['latitude'] ?? null),
                    $this->csvValue($row['longitude'] ?? null),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Validasi filter bertingkat dan tegakkan otorisasi kelas.
     *
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request, User $user): array
    {
        $validated = $request->validate([
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'student_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', Rule::in($this->statusValues())],
        ]);

        $filters = array_filter(
            $validated,
            fn (mixed $value): bool => $value !== null && $value !== '',
        );

        if (isset($filters['class_id'])) {
            $class = SchoolClass::query()->find((int) $filters['class_id']);

            if ($class instanceof SchoolClass) {
                Gate::authorize('view', $class);
            }
        }

        return $filters;
    }

    /**
     * @return array<string, string|null>
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'education_level_id' => $this->queryValue($request, 'education_level_id'),
            'class_id' => $this->queryValue($request, 'class_id'),
            'subject_id' => $this->queryValue($request, 'subject_id'),
            'student_id' => $this->queryValue($request, 'student_id'),
            'date_from' => $this->queryValue($request, 'date_from'),
            'date_to' => $this->queryValue($request, 'date_to'),
            'status' => $this->queryValue($request, 'status'),
        ];
    }

    private function queryValue(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (AttendanceStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            AttendanceStatus::cases(),
        );
    }

    /**
     * @return array<int, string>
     */
    private function statusValues(): array
    {
        return array_map(
            fn (AttendanceStatus $status): string => $status->value,
            AttendanceStatus::cases(),
        );
    }

    private function csvValue(mixed $value): string
    {
        if ($value === null || is_bool($value)) {
            return '';
        }

        return (string) $value;
    }
}
