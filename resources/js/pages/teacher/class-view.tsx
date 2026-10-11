import { useCallback, useEffect, useMemo, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import {
    AlertCircle,
    ChevronLeft,
    Clock,
    Loader2,
    Plus,
    RefreshCw,
} from 'lucide-react';
import { ApiError, apiRequest } from '@/lib/api';
import { encodeAttendanceQr } from '@/lib/attendance-qr';
import { dashboard } from '@/routes';
import attendance from '@/routes/attendance';
import type {
    AttendanceParticipant,
    AttendanceRecordStatus,
    AttendanceSession,
    TeacherAssignmentOption,
} from '@/types';

type ClassTab = 'qr' | 'monitor';
type ClassFilter = 'semua' | 'hadir' | 'bolos';

const POLL_INTERVAL_MS = 5000;

function toLocalInput(date: Date): string {
    const offset = date.getTimezoneOffset();
    const local = new Date(date.getTime() - offset * 60 * 1000);

    return local.toISOString().slice(0, 16);
}

function formatClock(iso: string): string {
    return new Date(iso).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatRange(session: AttendanceSession): string {
    return `${formatClock(session.start_at)}-${formatClock(session.end_at)}`;
}

function formatDuration(milliseconds: number): string {
    const totalSeconds = Math.max(0, Math.floor(milliseconds / 1000));
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    const pad = (value: number) => String(value).padStart(2, '0');

    return hours > 0
        ? `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`
        : `${pad(minutes)}:${pad(seconds)}`;
}

function statusBadge(status: AttendanceRecordStatus): {
    label: string;
    className: string;
} {
    switch (status) {
        case 'HADIR':
            return {
                label: 'Hadir',
                className: 'bg-emerald-100 text-emerald-700',
            };
        case 'TERLAMBAT':
            return {
                label: 'Terlambat',
                className: 'bg-amber-100 text-amber-700',
            };
        case 'IZIN':
            return { label: 'Izin', className: 'bg-gray-200 text-gray-600' };
        case 'SAKIT':
            return { label: 'Sakit', className: 'bg-gray-200 text-gray-600' };
        default:
            return { label: 'Belum', className: 'bg-rose-100 text-rose-700' };
    }
}

export default function TeacherClassView() {
    const [activeTab, setActiveTab] = useState<ClassTab>('qr');
    const [filter, setFilter] = useState<ClassFilter>('semua');
    const [sessions, setSessions] = useState<AttendanceSession[]>([]);
    const [assignments, setAssignments] = useState<TeacherAssignmentOption[]>(
        [],
    );
    const [selectedSessionId, setSelectedSessionId] = useState<number | null>(
        null,
    );
    const [participants, setParticipants] = useState<AttendanceParticipant[]>(
        [],
    );
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [now, setNow] = useState(() => Date.now());

    const [showCreate, setShowCreate] = useState(false);
    const [assignmentId, setAssignmentId] = useState('');
    const [startAt, setStartAt] = useState(() => toLocalInput(new Date()));
    const [endAt, setEndAt] = useState(() =>
        toLocalInput(new Date(Date.now() + 2 * 60 * 60 * 1000)),
    );
    const [isCreating, setIsCreating] = useState(false);
    const [createError, setCreateError] = useState<string | null>(null);

    const selectedSession = useMemo(
        () =>
            sessions.find((session) => session.id === selectedSessionId) ??
            null,
        [sessions, selectedSessionId],
    );

    const loadSessions = useCallback(async () => {
        setIsLoading(true);

        try {
            const [sessionsResponse, assignmentsResponse] = await Promise.all([
                apiRequest<{ data: AttendanceSession[] }>(
                    attendance.sessions.index().url,
                ),
                apiRequest<{ data: TeacherAssignmentOption[] }>(
                    attendance.assignments.index().url,
                ),
            ]);

            setSessions(sessionsResponse.data);
            setAssignments(assignmentsResponse.data);
            setSelectedSessionId((previous) => {
                if (
                    previous &&
                    sessionsResponse.data.some(
                        (session) => session.id === previous,
                    )
                ) {
                    return previous;
                }

                const currentTime = Date.now();
                const active = sessionsResponse.data.find(
                    (session) =>
                        new Date(session.start_at).getTime() <= currentTime &&
                        currentTime <= new Date(session.end_at).getTime(),
                );

                return active?.id ?? sessionsResponse.data[0]?.id ?? null;
            });
            setError(null);
        } catch (requestError) {
            setError(
                requestError instanceof ApiError
                    ? requestError.message
                    : 'Gagal memuat data sesi absensi.',
            );
        } finally {
            setIsLoading(false);
        }
    }, []);

    const loadSummary = useCallback(async (sessionId: number) => {
        try {
            const response = await apiRequest<{
                data: {
                    session: AttendanceSession;
                    participants: AttendanceParticipant[];
                };
            }>(attendance.sessions.show({ attendanceSession: sessionId }).url);

            setParticipants(response.data.participants);
            setSessions((previous) =>
                previous.map((session) =>
                    session.id === sessionId ? response.data.session : session,
                ),
            );
        } catch {
            // Poling berikutnya akan mencoba lagi.
        }
    }, []);

    useEffect(() => {
        void loadSessions();
    }, [loadSessions]);

    useEffect(() => {
        if (!selectedSessionId) {
            setParticipants([]);

            return;
        }

        void loadSummary(selectedSessionId);

        const timer = window.setInterval(
            () => void loadSummary(selectedSessionId),
            POLL_INTERVAL_MS,
        );

        return () => window.clearInterval(timer);
    }, [selectedSessionId, loadSummary]);

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(timer);
    }, []);

    useEffect(() => {
        if (!assignmentId && assignments.length > 0) {
            setAssignmentId(String(assignments[0].id));
        }
    }, [assignments, assignmentId]);

    const stats = useMemo(() => {
        const hadir = participants.filter(
            (participant) =>
                participant.status === 'HADIR' ||
                participant.status === 'TERLAMBAT',
        ).length;
        const izin = participants.filter(
            (participant) =>
                participant.status === 'IZIN' || participant.status === 'SAKIT',
        ).length;
        const belum = participants.filter(
            (participant) => participant.status === 'TIDAK_HADIR',
        ).length;

        return { hadir, izin, belum, total: participants.length };
    }, [participants]);

    const filteredParticipants = participants.filter((participant) => {
        if (filter === 'hadir') {
            return (
                participant.status === 'HADIR' ||
                participant.status === 'TERLAMBAT'
            );
        }

        if (filter === 'bolos') {
            return participant.status === 'TIDAK_HADIR';
        }

        return true;
    });

    const windowState = useMemo(() => {
        if (!selectedSession) {
            return { active: false, label: 'Tidak ada sesi' };
        }

        const start = new Date(selectedSession.start_at).getTime();
        const end = new Date(selectedSession.end_at).getTime();

        if (now < start) {
            return {
                active: false,
                label: `Dibuka dalam ${formatDuration(start - now)}`,
            };
        }

        if (now > end) {
            return { active: false, label: 'Sesi berakhir' };
        }

        return { active: true, label: formatDuration(end - now) };
    }, [selectedSession, now]);

    const qrValue = selectedSession
        ? encodeAttendanceQr({
              sessionId: selectedSession.id,
              qrPayload: selectedSession.qr_payload,
          })
        : '';

    const handleCreateSession = async (event: React.FormEvent) => {
        event.preventDefault();

        if (!assignmentId) {
            setCreateError('Pilih mata pelajaran terlebih dahulu.');

            return;
        }

        setIsCreating(true);
        setCreateError(null);

        try {
            const response = await apiRequest<{ data: AttendanceSession }>(
                attendance.sessions.store().url,
                {
                    method: 'POST',
                    body: {
                        assignment_id: Number(assignmentId),
                        start_at: new Date(startAt).toISOString(),
                        end_at: endAt
                            ? new Date(endAt).toISOString()
                            : undefined,
                    },
                },
            );

            setSessions((previous) => [response.data, ...previous]);
            setSelectedSessionId(response.data.id);
            setShowCreate(false);
        } catch (requestError) {
            setCreateError(
                requestError instanceof ApiError
                    ? (requestError.firstError('assignment_id') ??
                          requestError.firstError('start_at') ??
                          requestError.message)
                    : 'Gagal membuka sesi absensi.',
            );
        } finally {
            setIsCreating(false);
        }
    };

    return (
        <>
            <Head title="Tampilan Guru - SiAbsen" />
            <div className="relative mx-auto min-h-screen max-w-md bg-slate-50 pb-6 shadow-2xl">
                <div className="relative flex items-center gap-3 overflow-hidden bg-[#1E3A8A] px-4 pt-12 pb-6">
                    <Link
                        href={dashboard()}
                        className="text-white hover:opacity-80"
                    >
                        <ChevronLeft size={28} />
                    </Link>
                    <div>
                        <p className="text-xs text-blue-100">
                            {activeTab === 'qr'
                                ? 'Absensi Kelas'
                                : selectedSession
                                  ? `${selectedSession.class_name ?? 'Kelas'} ${formatRange(selectedSession)}`
                                  : 'Belum ada sesi aktif'}
                        </p>
                        <h1 className="text-xl font-bold tracking-wide text-white">
                            {activeTab === 'qr'
                                ? 'QR CODE KELAS'
                                : 'Monitor Kehadiran'}
                        </h1>
                    </div>
                    <button
                        type="button"
                        onClick={() => void loadSessions()}
                        className="ml-auto text-white/80 hover:text-white"
                        title="Muat ulang"
                    >
                        <RefreshCw size={18} />
                    </button>
                </div>

                {isLoading ? (
                    <div className="flex flex-col items-center justify-center py-24 text-gray-400">
                        <Loader2 size={32} className="animate-spin" />
                        <p className="mt-3 text-xs">Memuat sesi absensi...</p>
                    </div>
                ) : error ? (
                    <div className="mx-5 mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                        {error}
                    </div>
                ) : activeTab === 'qr' ? (
                    <div className="mt-6 space-y-4 px-5">
                        <div className="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                            <div>
                                <h3 className="font-bold text-blue-600">
                                    {selectedSession?.class_name ??
                                        'Belum ada sesi'}
                                </h3>
                                <p className="mt-1 text-xs text-gray-500">
                                    {selectedSession
                                        ? formatRange(selectedSession)
                                        : 'Buka sesi untuk memulai absensi'}
                                </p>
                            </div>
                            <span
                                className={`rounded-md px-3 py-1 text-xs font-bold ${
                                    windowState.active
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-gray-200 text-gray-600'
                                }`}
                            >
                                {windowState.active ? 'Aktif' : 'Nonaktif'}
                            </span>
                        </div>

                        {selectedSession && (
                            <div className="flex items-center justify-center gap-2 rounded-xl border border-blue-100 bg-white py-3">
                                <Clock size={16} className="text-[#1E3A8A]" />
                                <span className="text-sm font-semibold text-[#1E3A8A]">
                                    {windowState.label}
                                </span>
                            </div>
                        )}

                        <div className="flex min-h-[300px] flex-col items-center justify-center rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
                            {selectedSession ? (
                                <div className="flex flex-col items-center">
                                    <div className="rounded-2xl bg-white p-3 shadow-inner ring-1 ring-slate-200">
                                        <QRCodeSVG
                                            value={qrValue}
                                            size={208}
                                            marginSize={2}
                                            level="M"
                                        />
                                    </div>
                                    <span className="mt-3 font-mono text-[10px] tracking-widest text-slate-400">
                                        TOKEN: {selectedSession.qr_payload}
                                    </span>
                                </div>
                            ) : (
                                <div className="text-center text-gray-400">
                                    <p className="text-sm font-medium">
                                        Belum ada sesi absensi
                                    </p>
                                    <p className="mt-1 text-xs">
                                        Buka sesi baru untuk menampilkan QR.
                                    </p>
                                </div>
                            )}
                        </div>

                        <div className="rounded-xl border border-gray-800 bg-white p-4">
                            <p className="text-center text-xs font-medium text-gray-800">
                                Setiap token hanya valid untuk satu sesi dan
                                sekali per siswa. Screenshot tidak dapat
                                digunakan.
                            </p>
                        </div>

                        <div className="grid grid-cols-3 gap-3 pt-2">
                            {[
                                { label: 'Hadir', count: stats.hadir },
                                { label: 'Belum Hadir', count: stats.belum },
                                { label: 'Izin', count: stats.izin },
                            ].map((stat) => (
                                <div
                                    key={stat.label}
                                    className="flex flex-col items-center justify-center rounded-xl border border-gray-100 bg-white p-4 shadow-sm"
                                >
                                    <p className="text-xl font-bold text-[#1E3A8A]">
                                        {stat.count}
                                    </p>
                                    <p className="mt-1 text-[10px] font-bold text-[#1E3A8A]">
                                        {stat.label}
                                    </p>
                                </div>
                            ))}
                        </div>

                        <div className="flex gap-4 pt-4">
                            <button
                                type="button"
                                onClick={() => setActiveTab('monitor')}
                                className="flex-1 rounded-xl bg-[#1E3A8A] py-4 font-semibold text-white shadow-md transition-transform active:scale-95"
                            >
                                Monitor Live
                            </button>
                            <button
                                type="button"
                                onClick={() => setShowCreate((value) => !value)}
                                className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-[#1E3A8A] py-4 font-semibold text-white shadow-md transition-transform active:scale-95"
                            >
                                <Plus size={18} /> Buka Sesi
                            </button>
                        </div>

                        {showCreate && (
                            <form
                                onSubmit={handleCreateSession}
                                className="space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
                            >
                                <p className="text-sm font-bold text-[#1E3A8A]">
                                    Buka Sesi Absensi
                                </p>

                                <div>
                                    <label className="mb-1 block text-xs font-bold text-gray-500">
                                        MATA PELAJARAN
                                    </label>
                                    <select
                                        value={assignmentId}
                                        onChange={(event) =>
                                            setAssignmentId(event.target.value)
                                        }
                                        className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-[#1E3A8A] focus:outline-none"
                                    >
                                        {assignments.length === 0 && (
                                            <option value="">
                                                Tidak ada penugasan
                                            </option>
                                        )}
                                        {assignments.map((assignment) => (
                                            <option
                                                key={assignment.id}
                                                value={assignment.id}
                                            >
                                                {assignment.subject_name} -{' '}
                                                {assignment.class_name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="mb-1 block text-xs font-bold text-gray-500">
                                            MULAI
                                        </label>
                                        <input
                                            type="datetime-local"
                                            value={startAt}
                                            onChange={(event) =>
                                                setStartAt(event.target.value)
                                            }
                                            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-[#1E3A8A] focus:outline-none"
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-bold text-gray-500">
                                            SELESAI
                                        </label>
                                        <input
                                            type="datetime-local"
                                            value={endAt}
                                            onChange={(event) =>
                                                setEndAt(event.target.value)
                                            }
                                            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-[#1E3A8A] focus:outline-none"
                                        />
                                    </div>
                                </div>

                                {createError && (
                                    <p className="text-xs font-medium text-rose-600">
                                        {createError}
                                    </p>
                                )}

                                <button
                                    type="submit"
                                    disabled={isCreating}
                                    className="w-full rounded-lg bg-[#1E3A8A] py-3 font-semibold text-white disabled:opacity-60"
                                >
                                    {isCreating
                                        ? 'Membuka sesi...'
                                        : 'Buka Sesi Absensi'}
                                </button>
                            </form>
                        )}
                    </div>
                ) : (
                    <div>
                        <div className="flex gap-2 overflow-x-auto border-b border-gray-200 bg-white px-4 py-4">
                            {(
                                [
                                    {
                                        key: 'semua',
                                        label: 'Semua',
                                        count: stats.total,
                                    },
                                    {
                                        key: 'hadir',
                                        label: 'Hadir',
                                        count: stats.hadir,
                                    },
                                    {
                                        key: 'bolos',
                                        label: 'Bolos',
                                        count: stats.belum,
                                    },
                                ] as const
                            ).map((item) => (
                                <button
                                    key={item.key}
                                    type="button"
                                    onClick={() => setFilter(item.key)}
                                    className={`flex items-center gap-2 rounded-full px-4 py-2 text-xs font-bold ${
                                        filter === item.key
                                            ? 'bg-blue-600 text-white'
                                            : 'border border-gray-300 text-gray-600'
                                    }`}
                                >
                                    {item.label}
                                    <span className="rounded-full bg-black/10 px-1.5 py-0.5 text-[10px]">
                                        {item.count}
                                    </span>
                                </button>
                            ))}
                            <button
                                type="button"
                                onClick={() => setActiveTab('qr')}
                                className="ml-auto px-2 py-2 text-xs font-semibold text-blue-600"
                            >
                                &larr; QR Code
                            </button>
                        </div>

                        <div className="flex items-center gap-3 border-b border-gray-200 bg-gray-100 px-4 py-2 text-sm text-gray-500">
                            <div className="rounded bg-blue-600 p-1 text-white">
                                <AlertCircle size={16} />
                            </div>
                            {stats.belum} murid belum absen
                        </div>

                        <div className="space-y-3 p-4">
                            {selectedSession ? (
                                filteredParticipants.length > 0 ? (
                                    filteredParticipants.map((participant) => {
                                        const badge = statusBadge(
                                            participant.status,
                                        );

                                        return (
                                            <div
                                                key={participant.student_id}
                                                className="flex items-center justify-between rounded-xl border-l-4 border-l-gray-200 bg-white p-4 shadow-sm"
                                            >
                                                <div>
                                                    <h4 className="text-sm font-bold text-gray-900">
                                                        {participant.name}
                                                    </h4>
                                                    <p className="mt-1 text-xs text-gray-500">
                                                        NIS{' '}
                                                        {participant.student_number ??
                                                            '-'}
                                                    </p>
                                                </div>
                                                <div className="flex flex-col items-end gap-1">
                                                    {participant.scanned_at && (
                                                        <p className="text-[10px] text-gray-500">
                                                            {formatClock(
                                                                participant.scanned_at,
                                                            )}
                                                        </p>
                                                    )}
                                                    <div className="flex items-center gap-2">
                                                        {participant.location_status ===
                                                            'DI_LUAR_RADIUS' && (
                                                            <AlertCircle
                                                                size={14}
                                                                className="text-amber-500"
                                                            />
                                                        )}
                                                        <span
                                                            className={`rounded-md px-3 py-1 text-[10px] font-bold ${badge.className}`}
                                                        >
                                                            {badge.label}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })
                                ) : (
                                    <p className="py-10 text-center text-sm text-gray-400">
                                        Tidak ada data untuk filter ini.
                                    </p>
                                )
                            ) : (
                                <p className="py-10 text-center text-sm text-gray-400">
                                    Belum ada sesi absensi yang dipilih.
                                </p>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
