import { useCallback, useEffect, useMemo, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Download, ExternalLink, Loader2, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { apiRequest } from '@/lib/api';
import academic from '@/routes/academic';
import attendance from '@/routes/attendance';
import reports from '@/routes/reports';
import type {
    AcademicClassOption,
    AcademicStudentOption,
    AttendanceRecordStatus,
    LocationStatus,
    ReportFilterOption,
    ReportFilters,
    ReportResult,
    ReportRow,
    ReportStatusOption,
    TeacherAssignmentOption,
} from '@/types';

type PageProps = {
    educationLevels: ReportFilterOption[];
    statuses: ReportStatusOption[];
    filters: ReportFilters;
};

const EMPTY_FILTERS: ReportFilters = {
    education_level_id: '',
    class_id: '',
    subject_id: '',
    student_id: '',
    date_from: '',
    date_to: '',
    status: '',
};

function toQuery(filters: ReportFilters): Record<string, string> {
    return Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== ''),
    );
}

function statusBadge(status: AttendanceRecordStatus): string {
    switch (status) {
        case 'HADIR':
            return 'bg-emerald-100 text-emerald-700';
        case 'TERLAMBAT':
            return 'bg-amber-100 text-amber-700';
        case 'IZIN':
            return 'bg-sky-100 text-sky-700';
        case 'SAKIT':
            return 'bg-violet-100 text-violet-700';
        default:
            return 'bg-rose-100 text-rose-700';
    }
}

function locationBadge(status: LocationStatus | null): string {
    switch (status) {
        case 'SESUAI':
            return 'bg-emerald-100 text-emerald-700';
        case 'DI_LUAR_RADIUS':
            return 'bg-rose-100 text-rose-700';
        default:
            return 'bg-gray-200 text-gray-600';
    }
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function formatTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function mapLink(row: ReportRow): string | null {
    if (row.latitude === null || row.longitude === null) {
        return null;
    }

    return `https://www.google.com/maps?q=${row.latitude},${row.longitude}`;
}

export default function ReportIndex({
    educationLevels,
    statuses,
    filters,
}: PageProps) {
    const [filterState, setFilterState] = useState<ReportFilters>({
        ...EMPTY_FILTERS,
        ...filters,
    });
    const [classes, setClasses] = useState<AcademicClassOption[]>([]);
    const [students, setStudents] = useState<AcademicStudentOption[]>([]);
    const [assignments, setAssignments] = useState<TeacherAssignmentOption[]>(
        [],
    );
    const [result, setResult] = useState<ReportResult | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const updateFilter = (key: keyof ReportFilters, value: string) => {
        setFilterState((previous) => {
            const next = { ...previous, [key]: value };

            if (key === 'education_level_id') {
                next.class_id = '';
                next.student_id = '';
            }

            if (key === 'class_id') {
                next.student_id = '';
                next.subject_id = '';
            }

            return next;
        });
    };

    useEffect(() => {
        let cancelled = false;

        const load = async () => {
            try {
                const response = await apiRequest<{
                    data: AcademicClassOption[];
                }>(
                    academic.search.url({
                        query: filterState.education_level_id
                            ? {
                                  education_level_id:
                                      filterState.education_level_id,
                              }
                            : {},
                    }),
                );

                if (!cancelled) {
                    setClasses(response.data);
                }
            } catch {
                if (!cancelled) {
                    setClasses([]);
                }
            }
        };

        void load();

        return () => {
            cancelled = true;
        };
    }, [filterState.education_level_id]);

    useEffect(() => {
        let cancelled = false;

        const load = async () => {
            if (!filterState.class_id) {
                setStudents([]);

                return;
            }

            try {
                const response = await apiRequest<{
                    data: AcademicStudentOption[];
                }>(
                    academic.search.url({
                        query: { class_id: filterState.class_id },
                    }),
                );

                if (!cancelled) {
                    setStudents(response.data);
                }
            } catch {
                if (!cancelled) {
                    setStudents([]);
                }
            }
        };

        void load();

        return () => {
            cancelled = true;
        };
    }, [filterState.class_id]);

    useEffect(() => {
        let cancelled = false;

        const load = async () => {
            try {
                const response = await apiRequest<{
                    data: TeacherAssignmentOption[];
                }>(attendance.assignments.index().url);

                if (!cancelled) {
                    setAssignments(response.data);
                }
            } catch {
                if (!cancelled) {
                    setAssignments([]);
                }
            }
        };

        void load();

        return () => {
            cancelled = true;
        };
    }, []);

    const subjects = useMemo(() => {
        const relevant = filterState.class_id
            ? assignments.filter(
                  (assignment) =>
                      String(assignment.class_id) === filterState.class_id,
              )
            : assignments;

        const unique = new Map<number, string>();

        relevant.forEach((assignment) => {
            unique.set(
                assignment.subject_id,
                assignment.subject_name ?? 'Tanpa nama',
            );
        });

        return Array.from(unique, ([id, name]) => ({ id, name }));
    }, [assignments, filterState.class_id]);

    const loadReport = useCallback(async () => {
        setIsLoading(true);

        try {
            const response = await apiRequest<{ data: ReportResult }>(
                reports.data.url({ query: toQuery(filterState) }),
            );

            setResult(response.data);
            setError(null);
        } catch {
            setError('Gagal memuat rekap absensi.');
        } finally {
            setIsLoading(false);
        }
    }, [filterState]);

    useEffect(() => {
        void loadReport();
    }, [loadReport]);

    const exportUrl = reports.export.url({ query: toQuery(filterState) });
    const summary = result?.summary;

    return (
        <>
            <Head title="Rekap Absensi - SiAbsen" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-[#1E3A8A]">
                            Rekap Absensi
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Filter bertingkat: jenjang, kelas, mata pelajaran,
                            periode, siswa, dan status.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={reports.search()}>
                                <Search /> Pencarian Bertingkat
                            </Link>
                        </Button>
                        <Button asChild>
                            <a href={exportUrl}>
                                <Download /> Ekspor CSV
                            </a>
                        </Button>
                    </div>
                </div>

                <div className="bg-card grid gap-3 rounded-xl border p-4 md:grid-cols-3 lg:grid-cols-6">
                    <label className="text-muted-foreground flex flex-col gap-1 text-xs font-semibold">
                        Jenjang
                        <select
                            value={filterState.education_level_id}
                            onChange={(event) =>
                                updateFilter(
                                    'education_level_id',
                                    event.target.value,
                                )
                            }
                            className="bg-background h-9 rounded-md border px-2 text-sm"
                        >
                            <option value="">Semua jenjang</option>
                            {educationLevels.map((level) => (
                                <option key={level.id} value={level.id}>
                                    {level.name}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="text-muted-foreground flex flex-col gap-1 text-xs font-semibold">
                        Kelas
                        <select
                            value={filterState.class_id}
                            onChange={(event) =>
                                updateFilter('class_id', event.target.value)
                            }
                            className="bg-background h-9 rounded-md border px-2 text-sm"
                        >
                            <option value="">Semua kelas</option>
                            {classes.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="text-muted-foreground flex flex-col gap-1 text-xs font-semibold">
                        Mata Pelajaran
                        <select
                            value={filterState.subject_id}
                            onChange={(event) =>
                                updateFilter('subject_id', event.target.value)
                            }
                            className="bg-background h-9 rounded-md border px-2 text-sm"
                        >
                            <option value="">Semua mapel</option>
                            {subjects.map((subject) => (
                                <option key={subject.id} value={subject.id}>
                                    {subject.name}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="text-muted-foreground flex flex-col gap-1 text-xs font-semibold">
                        Siswa
                        <select
                            value={filterState.student_id}
                            onChange={(event) =>
                                updateFilter('student_id', event.target.value)
                            }
                            className="bg-background h-9 rounded-md border px-2 text-sm"
                            disabled={!filterState.class_id}
                        >
                            <option value="">Semua siswa</option>
                            {students.map((student) => (
                                <option key={student.id} value={student.id}>
                                    {student.name}
                                    {student.student_number
                                        ? ` (${student.student_number})`
                                        : ''}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="text-muted-foreground flex flex-col gap-1 text-xs font-semibold">
                        Status
                        <select
                            value={filterState.status}
                            onChange={(event) =>
                                updateFilter('status', event.target.value)
                            }
                            className="bg-background h-9 rounded-md border px-2 text-sm"
                        >
                            <option value="">Semua status</option>
                            {statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                    </label>

                    <div className="grid grid-cols-2 gap-2">
                        <label className="text-muted-foreground flex flex-col gap-1 text-xs font-semibold">
                            Dari
                            <input
                                type="date"
                                value={filterState.date_from}
                                onChange={(event) =>
                                    updateFilter(
                                        'date_from',
                                        event.target.value,
                                    )
                                }
                                className="bg-background h-9 rounded-md border px-2 text-sm"
                            />
                        </label>
                        <label className="text-muted-foreground flex flex-col gap-1 text-xs font-semibold">
                            Sampai
                            <input
                                type="date"
                                value={filterState.date_to}
                                onChange={(event) =>
                                    updateFilter('date_to', event.target.value)
                                }
                                className="bg-background h-9 rounded-md border px-2 text-sm"
                            />
                        </label>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-6">
                    {[
                        { label: 'Hadir', value: summary?.hadir ?? 0 },
                        { label: 'Terlambat', value: summary?.terlambat ?? 0 },
                        {
                            label: 'Tidak Hadir',
                            value: summary?.tidak_hadir ?? 0,
                        },
                        { label: 'Izin', value: summary?.izin ?? 0 },
                        { label: 'Sakit', value: summary?.sakit ?? 0 },
                        {
                            label: 'Kehadiran',
                            value: `${summary?.persentase_kehadiran ?? 0}%`,
                        },
                    ].map((item) => (
                        <div
                            key={item.label}
                            className="bg-card rounded-xl border p-4"
                        >
                            <p className="text-muted-foreground text-xs font-semibold">
                                {item.label}
                            </p>
                            <p className="mt-1 text-2xl font-bold text-[#1E3A8A]">
                                {item.value}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="bg-card overflow-hidden rounded-xl border">
                    {isLoading ? (
                        <div className="text-muted-foreground flex items-center justify-center gap-2 py-16 text-sm">
                            <Loader2 className="animate-spin" /> Memuat rekap...
                        </div>
                    ) : error ? (
                        <p className="py-16 text-center text-sm text-rose-600">
                            {error}
                        </p>
                    ) : result && result.rows.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-xs uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Tanggal</th>
                                        <th className="px-4 py-3">Kelas</th>
                                        <th className="px-4 py-3">Mapel</th>
                                        <th className="px-4 py-3">NIS</th>
                                        <th className="px-4 py-3">Siswa</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3">Waktu</th>
                                        <th className="px-4 py-3">Lokasi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {result.rows.map((row, index) => {
                                        const link = mapLink(row);

                                        return (
                                            <tr
                                                key={`${row.session_id}-${row.student_id}-${index}`}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    {formatDate(
                                                        row.session_date,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {row.class_name ?? '-'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {row.subject_name ?? '-'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {row.student_number ?? '-'}
                                                </td>
                                                <td className="px-4 py-3 font-medium">
                                                    {row.student_name}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span
                                                        className={`rounded-md px-2 py-1 text-xs font-bold ${statusBadge(row.status)}`}
                                                    >
                                                        {row.status_label}
                                                        {row.late_minutes
                                                            ? ` +${row.late_minutes}m`
                                                            : ''}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    {formatTime(row.scanned_at)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <span
                                                            className={`rounded-md px-2 py-1 text-xs font-semibold ${locationBadge(row.location_status)}`}
                                                        >
                                                            {row.location_label ??
                                                                'Tidak Terverifikasi'}
                                                        </span>
                                                        {row.distance_meters !==
                                                            null && (
                                                            <span className="text-muted-foreground text-xs">
                                                                {
                                                                    row.distance_meters
                                                                }
                                                                m
                                                            </span>
                                                        )}
                                                        {link && (
                                                            <a
                                                                href={link}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="text-[#1E3A8A] hover:underline"
                                                                title="Buka peta"
                                                            >
                                                                <ExternalLink
                                                                    size={14}
                                                                />
                                                            </a>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="text-muted-foreground py-16 text-center text-sm">
                            Tidak ada data untuk filter yang dipilih.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}
