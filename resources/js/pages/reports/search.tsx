import { useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BarChart3, Loader2, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { apiRequest } from '@/lib/api';
import academic from '@/routes/academic';
import reports from '@/routes/reports';
import type {
    AcademicClassOption,
    AcademicStudentOption,
    ReportFilterOption,
} from '@/types';

type PageProps = {
    educationLevels: ReportFilterOption[];
};

export default function ReportSearch({ educationLevels }: PageProps) {
    const [educationLevelId, setEducationLevelId] = useState('');
    const [classId, setClassId] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [classes, setClasses] = useState<AcademicClassOption[]>([]);
    const [students, setStudents] = useState<AcademicStudentOption[]>([]);
    const [isLoadingClasses, setIsLoadingClasses] = useState(false);
    const [isLoadingStudents, setIsLoadingStudents] = useState(false);

    useEffect(() => {
        let cancelled = false;

        const load = async () => {
            setIsLoadingClasses(true);

            try {
                const response = await apiRequest<{
                    data: AcademicClassOption[];
                }>(
                    academic.search.url({
                        query: educationLevelId
                            ? { education_level_id: educationLevelId }
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
            } finally {
                if (!cancelled) {
                    setIsLoadingClasses(false);
                }
            }
        };

        void load();

        return () => {
            cancelled = true;
        };
    }, [educationLevelId]);

    useEffect(() => {
        let cancelled = false;

        const load = async () => {
            if (!classId) {
                setStudents([]);

                return;
            }

            setIsLoadingStudents(true);

            try {
                const response = await apiRequest<{
                    data: AcademicStudentOption[];
                }>(academic.search.url({ query: { class_id: classId } }));

                if (!cancelled) {
                    setStudents(response.data);
                }
            } catch {
                if (!cancelled) {
                    setStudents([]);
                }
            } finally {
                if (!cancelled) {
                    setIsLoadingStudents(false);
                }
            }
        };

        void load();

        return () => {
            cancelled = true;
        };
    }, [classId]);

    const periodQuery = {
        ...(dateFrom ? { date_from: dateFrom } : {}),
        ...(dateTo ? { date_to: dateTo } : {}),
    };

    return (
        <>
            <Head title="Pencarian Absensi - SiAbsen" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-[#1E3A8A]">
                            Pencarian Bertingkat
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Telusuri jenjang, kelas, hingga siswa untuk melihat
                            rekap kehadirannya.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={reports.index()}>
                            <ArrowLeft /> Kembali ke Rekap
                        </Link>
                    </Button>
                </div>

                <div className="bg-card grid gap-3 rounded-xl border p-4 md:grid-cols-4">
                    <label className="text-muted-foreground flex flex-col gap-1 text-xs font-semibold">
                        Jenjang
                        <select
                            value={educationLevelId}
                            onChange={(event) => {
                                setEducationLevelId(event.target.value);
                                setClassId('');
                            }}
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
                            value={classId}
                            onChange={(event) => setClassId(event.target.value)}
                            className="bg-background h-9 rounded-md border px-2 text-sm"
                        >
                            <option value="">Pilih kelas</option>
                            {classes.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="text-muted-foreground flex flex-col gap-1 text-xs font-semibold">
                        Dari
                        <input
                            type="date"
                            value={dateFrom}
                            onChange={(event) =>
                                setDateFrom(event.target.value)
                            }
                            className="bg-background h-9 rounded-md border px-2 text-sm"
                        />
                    </label>

                    <label className="text-muted-foreground flex flex-col gap-1 text-xs font-semibold">
                        Sampai
                        <input
                            type="date"
                            value={dateTo}
                            onChange={(event) => setDateTo(event.target.value)}
                            className="bg-background h-9 rounded-md border px-2 text-sm"
                        />
                    </label>
                </div>

                {classId && (
                    <div className="flex flex-wrap gap-2">
                        <Button asChild>
                            <Link
                                href={reports.index({
                                    query: {
                                        class_id: classId,
                                        ...periodQuery,
                                    },
                                })}
                            >
                                <BarChart3 /> Rekap Kelas
                            </Link>
                        </Button>
                    </div>
                )}

                <div className="bg-card overflow-hidden rounded-xl border">
                    <div className="bg-muted/40 flex items-center gap-2 border-b px-4 py-3 text-sm font-semibold text-[#1E3A8A]">
                        <Users size={16} />
                        Daftar Siswa
                        {students.length > 0 && (
                            <span className="text-muted-foreground">
                                ({students.length})
                            </span>
                        )}
                    </div>

                    {!classId ? (
                        <p className="text-muted-foreground py-16 text-center text-sm">
                            Pilih jenjang dan kelas terlebih dahulu.
                        </p>
                    ) : isLoadingClasses || isLoadingStudents ? (
                        <div className="text-muted-foreground flex items-center justify-center gap-2 py-16 text-sm">
                            <Loader2 className="animate-spin" /> Memuat data...
                        </div>
                    ) : students.length > 0 ? (
                        <ul className="divide-y">
                            {students.map((student) => (
                                <li
                                    key={student.id}
                                    className="hover:bg-muted/30 flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {student.name}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            NIS: {student.student_number ?? '-'}{' '}
                                            - {student.class_name}
                                        </p>
                                    </div>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={reports.index({
                                                query: {
                                                    class_id: classId,
                                                    student_id: student.id,
                                                    ...periodQuery,
                                                },
                                            })}
                                        >
                                            <BarChart3 /> Rekap Siswa
                                        </Link>
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-muted-foreground py-16 text-center text-sm">
                            Tidak ada siswa aktif di kelas ini.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}
