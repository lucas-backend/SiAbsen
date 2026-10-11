import { useEffect, useMemo, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Camera, LogOut, QrCode, UserCheck } from 'lucide-react';
import { apiRequest } from '@/lib/api';
import { logout } from '@/routes';
import attendance from '@/routes/attendance';
import reports from '@/routes/reports';
import student from '@/routes/student';
import teacher from '@/routes/teacher';
import type {
    AttendanceRecordStatus,
    AttendanceSession,
    Auth,
    StudentAttendanceHistoryItem,
} from '@/types';

type TimelineItem = {
    id: number;
    title: string;
    subtitle: string;
    time: string;
    label: string;
    color: string;
    active: boolean;
};

function formatClock(iso: string): string {
    return new Date(iso).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatRange(start: string, end: string): string {
    return `${formatClock(start)}-${formatClock(end)}`;
}

function formatDuration(milliseconds: number): string {
    const totalSeconds = Math.max(0, Math.floor(milliseconds / 1000));
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
}

function statusMeta(status: AttendanceRecordStatus): {
    label: string;
    color: string;
} {
    switch (status) {
        case 'HADIR':
            return { label: 'Hadir', color: 'bg-emerald-100 text-emerald-700' };
        case 'TERLAMBAT':
            return { label: 'Terlambat', color: 'bg-amber-100 text-amber-700' };
        case 'IZIN':
            return { label: 'Izin', color: 'bg-gray-200 text-gray-600' };
        case 'SAKIT':
            return { label: 'Sakit', color: 'bg-gray-200 text-gray-600' };
        default:
            return { label: 'Belum', color: 'bg-rose-100 text-rose-700' };
    }
}

function withinWindow(start: string, end: string, now: number): boolean {
    return new Date(start).getTime() <= now && now <= new Date(end).getTime();
}

export default function Dashboard() {
    const { auth } = usePage<{ auth?: Auth }>().props;
    const user = auth?.user;
    const role = user?.role ?? 'STUDENT';
    const isStudent = role === 'STUDENT';
    const roleLabel = isStudent
        ? 'Siswa'
        : role === 'TEACHER'
          ? 'Guru'
          : 'Admin';

    const [now, setNow] = useState(() => Date.now());
    const [history, setHistory] = useState<StudentAttendanceHistoryItem[]>([]);
    const [sessions, setSessions] = useState<AttendanceSession[]>([]);

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(timer);
    }, []);

    useEffect(() => {
        let cancelled = false;

        const load = async () => {
            try {
                if (isStudent) {
                    const response = await apiRequest<{
                        data: StudentAttendanceHistoryItem[];
                    }>(attendance.me().url);

                    if (!cancelled) {
                        setHistory(response.data);
                    }
                } else {
                    const response = await apiRequest<{
                        data: AttendanceSession[];
                    }>(attendance.sessions.index().url);

                    if (!cancelled) {
                        setSessions(response.data);
                    }
                }
            } catch {
                // Dashboard tetap tampil walau data gagal dimuat.
            }
        };

        void load();

        return () => {
            cancelled = true;
        };
    }, [isStudent]);

    const timeline = useMemo<TimelineItem[]>(() => {
        if (isStudent) {
            return history.slice(0, 5).map((item) => {
                const meta = statusMeta(item.status);

                return {
                    id: item.session_id,
                    title: 'Sesi Absensi',
                    subtitle: new Date(item.session_date).toLocaleDateString(
                        'id-ID',
                        { day: 'numeric', month: 'short', year: 'numeric' },
                    ),
                    time: formatRange(item.start_at, item.end_at),
                    label: meta.label,
                    color: meta.color,
                    active: withinWindow(item.start_at, item.end_at, now),
                };
            });
        }

        return sessions.slice(0, 5).map((session) => {
            const active = withinWindow(session.start_at, session.end_at, now);
            const finished = now > new Date(session.end_at).getTime();

            return {
                id: session.id,
                title: session.class_name ?? 'Sesi Absensi',
                subtitle: new Date(session.session_date).toLocaleDateString(
                    'id-ID',
                    { day: 'numeric', month: 'short', year: 'numeric' },
                ),
                time: formatRange(session.start_at, session.end_at),
                label: active ? 'Aktif' : finished ? 'Selesai' : 'Akan Datang',
                color: active
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-gray-200 text-gray-500',
                active,
            };
        });
    }, [isStudent, history, sessions, now]);

    const activeStudent = history.find((item) =>
        withinWindow(item.start_at, item.end_at, now),
    );
    const activeTeacher = sessions.find((session) =>
        withinWindow(session.start_at, session.end_at, now),
    );

    const activeTitle = isStudent
        ? 'Sesi Absensi'
        : (activeTeacher?.class_name ?? 'Belum Ada Kelas');
    const activeSubtitle = isStudent
        ? (activeStudent?.session_date ?? 'Tidak ada sesi berlangsung')
        : activeTeacher
          ? 'Sesi absensi kelas'
          : 'Tidak ada sesi berlangsung';
    const activeEndAt = isStudent
        ? activeStudent?.end_at
        : activeTeacher?.end_at;
    const remaining = activeEndAt
        ? formatDuration(new Date(activeEndAt).getTime() - now)
        : '--:--';

    return (
        <>
            <Head title="Dashboard - EduPresence" />
            <div className="relative mx-auto min-h-screen max-w-md bg-slate-50 pb-8 font-sans shadow-2xl">
                <div className="relative overflow-hidden rounded-br-[80px] bg-[#1E3A8A] px-6 pt-12 pb-16">
                    <div className="absolute top-0 right-0 -mt-20 -mr-20 h-64 w-64 rounded-full bg-white/5" />

                    <div className="relative z-10 mb-4 flex items-center justify-between">
                        <span className="flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-[11px] font-medium text-white">
                            <UserCheck size={13} />
                            {roleLabel}
                        </span>
                        <Link
                            href={logout()}
                            as="button"
                            className="p-1 text-blue-200 hover:text-white"
                            title="Keluar"
                        >
                            <LogOut size={18} />
                        </Link>
                    </div>

                    <p className="text-sm font-medium text-blue-100">
                        {new Date().toLocaleDateString('id-ID', {
                            weekday: 'long',
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric',
                        })}
                    </p>
                    <h1 className="mt-1 text-2xl font-bold text-white">
                        Selamat Datang, {user?.name ?? 'Pengguna'}
                    </h1>
                </div>

                <div className="relative z-10 -mt-10 px-5">
                    <div className="rounded-2xl border border-blue-800 bg-[#254BA0] p-5 shadow-lg">
                        <div className="mb-6 flex items-start justify-between">
                            <div>
                                <p className="text-xs tracking-wider text-blue-200">
                                    SEDANG BERLANGSUNG
                                </p>
                                <h2 className="mt-1 text-2xl font-bold text-white">
                                    {activeTitle}
                                </h2>
                                <p className="mt-1 text-sm text-blue-200">
                                    {activeSubtitle}
                                </p>
                            </div>
                            <div className="rounded-lg border border-[#2e5bbd] bg-[#1c3a82] px-3 py-2 text-center">
                                <p className="text-[10px] text-blue-200">
                                    Sisa Waktu
                                </p>
                                <p className="text-lg font-bold text-teal-400">
                                    {remaining}
                                </p>
                            </div>
                        </div>

                        {isStudent ? (
                            <Link
                                href={student.scan()}
                                className="flex w-full items-center justify-center gap-2 rounded-xl bg-white py-3.5 font-semibold text-gray-800 shadow-md transition-transform active:scale-95"
                            >
                                <Camera size={20} className="text-gray-500" />{' '}
                                Scan QR Absensi
                            </Link>
                        ) : (
                            <Link
                                href={teacher.class()}
                                className="flex w-full items-center justify-center gap-2 rounded-xl bg-white py-3.5 font-semibold text-gray-800 shadow-md transition-transform active:scale-95"
                            >
                                <QrCode size={20} className="text-gray-500" />{' '}
                                Generate QR Code
                            </Link>
                        )}
                    </div>
                </div>

                <div className="mt-4 grid grid-cols-2 gap-3 px-5">
                    <Link
                        href={student.scan()}
                        className="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-3 text-xs font-semibold text-[#1E3A8A] shadow-xs hover:bg-blue-50/50"
                    >
                        <span>Halaman Scan QR</span>
                        <ArrowRight size={14} />
                    </Link>
                    <Link
                        href={teacher.class()}
                        className="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-3 text-xs font-semibold text-[#1E3A8A] shadow-xs hover:bg-blue-50/50"
                    >
                        <span>Tampilan Guru</span>
                        <ArrowRight size={14} />
                    </Link>
                    {!isStudent && (
                        <Link
                            href={reports.index()}
                            className="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-3 text-xs font-semibold text-[#1E3A8A] shadow-xs hover:bg-blue-50/50"
                        >
                            <span>Rekap Absensi</span>
                            <ArrowRight size={14} />
                        </Link>
                    )}
                </div>

                <div className="mt-6 px-5">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="font-bold text-[#1E3A8A]">
                            Jadwal Hari Ini
                        </h3>
                        {isStudent && (
                            <Link
                                href={student.permission()}
                                className="text-xs font-semibold text-blue-600 hover:underline"
                            >
                                + Buat Izin
                            </Link>
                        )}
                    </div>

                    {timeline.length === 0 ? (
                        <p className="py-6 text-center text-xs text-gray-400">
                            Belum ada jadwal absensi.
                        </p>
                    ) : (
                        <div className="relative ml-3 space-y-4 border-l-2 border-gray-200 pb-6">
                            {timeline.map((item) => (
                                <div key={item.id} className="relative pl-6">
                                    <span
                                        className={`absolute top-4 -left-[7px] h-3 w-3 rounded-full border-2 border-white ${
                                            item.active
                                                ? 'bg-blue-600'
                                                : item.label === 'Hadir'
                                                  ? 'bg-teal-400'
                                                  : 'bg-gray-300'
                                        }`}
                                    />
                                    <div
                                        className={`flex items-center justify-between rounded-xl p-4 ${
                                            item.active
                                                ? 'border border-blue-100 bg-white shadow-sm'
                                                : 'bg-transparent'
                                        }`}
                                    >
                                        <div>
                                            <h4
                                                className={`font-bold ${
                                                    item.active
                                                        ? 'text-blue-600'
                                                        : 'text-teal-600'
                                                }`}
                                            >
                                                {item.title}
                                            </h4>
                                            <p className="mt-1 text-xs text-gray-500">
                                                {item.subtitle}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="mb-1 text-[10px] text-gray-400">
                                                {item.time}
                                            </p>
                                            <span
                                                className={`rounded-md px-3 py-1 text-[10px] font-bold ${item.color}`}
                                            >
                                                {item.label}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
