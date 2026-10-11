import { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronLeft } from 'lucide-react';
import { toast } from 'sonner';
import { ApiError, apiRequest } from '@/lib/api';
import { dashboard } from '@/routes';
import attendance from '@/routes/attendance';
import type { Auth, StudentAttendanceHistoryItem } from '@/types';

type RequestedStatus = 'IZIN' | 'SAKIT';

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

export default function PermissionForm() {
    const { auth } = usePage<{ auth?: Auth }>().props;
    const user = auth?.user;

    const [sessions, setSessions] = useState<StudentAttendanceHistoryItem[]>(
        [],
    );
    const [sessionId, setSessionId] = useState('');
    const [requestedStatus, setRequestedStatus] =
        useState<RequestedStatus>('IZIN');
    const [reason, setReason] = useState('');
    const [isLoadingSessions, setIsLoadingSessions] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isSubmitted, setIsSubmitted] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        const load = async () => {
            try {
                const response = await apiRequest<{
                    data: StudentAttendanceHistoryItem[];
                }>(attendance.me().url);

                if (!cancelled) {
                    setSessions(response.data);
                    setSessionId(
                        (previous) =>
                            previous ||
                            String(response.data[0]?.session_id ?? ''),
                    );
                }
            } catch {
                if (!cancelled) {
                    setError('Gagal memuat daftar sesi absensi.');
                }
            } finally {
                if (!cancelled) {
                    setIsLoadingSessions(false);
                }
            }
        };

        void load();

        return () => {
            cancelled = true;
        };
    }, []);

    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault();

        if (!reason.trim()) {
            setError('Silakan tuliskan alasan izin terlebih dahulu.');

            return;
        }

        if (!sessionId) {
            setError('Pilih sesi absensi terlebih dahulu.');

            return;
        }

        setIsSubmitting(true);
        setError(null);

        try {
            await apiRequest(attendance.requests.store().url, {
                method: 'POST',
                body: {
                    session_id: Number(sessionId),
                    requested_status: requestedStatus,
                    reason: reason.trim(),
                },
            });

            setIsSubmitted(true);
            toast.success('Permohonan izin berhasil dikirim.');
            window.setTimeout(() => router.visit(dashboard()), 1200);
        } catch (requestError) {
            setError(
                requestError instanceof ApiError
                    ? (requestError.firstError('reason') ??
                          requestError.firstError('session_id') ??
                          requestError.message)
                    : 'Gagal mengirim permohonan izin.',
            );
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <>
            <Head title="Permintaan Izin - EduPresence" />
            <div className="relative mx-auto flex min-h-screen max-w-md flex-col bg-slate-50 font-sans shadow-2xl">
                <div className="relative flex shrink-0 items-center gap-3 overflow-hidden rounded-b-2xl bg-[#1E3A8A] px-4 pt-12 pb-6">
                    <div className="absolute top-0 right-0 -mt-20 -mr-20 h-64 w-64 rounded-full bg-white/5" />
                    <Link
                        href={dashboard()}
                        className="relative z-10 text-white hover:opacity-80"
                    >
                        <ChevronLeft size={28} />
                    </Link>
                    <div className="relative z-10">
                        <p className="text-xs font-medium text-blue-100">
                            Permintaan Izin
                        </p>
                        <h1 className="text-xl font-bold tracking-wide text-white">
                            {user?.name ?? 'Siswa'}
                        </h1>
                    </div>
                </div>

                {isSubmitted ? (
                    <div className="flex flex-1 flex-col items-center justify-center px-8 text-center">
                        <CheckCircle2
                            size={96}
                            className="mb-6 text-emerald-500"
                            strokeWidth={1.5}
                        />
                        <h2 className="text-2xl font-bold text-gray-900">
                            Permohonan Terkirim
                        </h2>
                        <p className="mt-2 text-sm text-gray-500">
                            Permohonan izin Anda sedang menunggu persetujuan
                            guru.
                        </p>
                    </div>
                ) : (
                    <form
                        onSubmit={handleSubmit}
                        className="flex flex-1 flex-col justify-between px-5 pt-6 pb-8"
                    >
                        <div>
                            <div className="mb-6 flex items-center justify-between rounded-xl border border-l-4 border-gray-100 border-l-rose-200 bg-white p-4 shadow-sm">
                                <div>
                                    <h3 className="text-sm font-bold text-gray-900">
                                        {user?.name ?? 'Siswa'}
                                    </h3>
                                    <p className="mt-1 text-xs text-gray-500">
                                        {user?.email}
                                    </p>
                                </div>
                            </div>

                            <div className="mb-4">
                                <label className="mb-2 block text-xs font-bold text-gray-500">
                                    SESI ABSENSI
                                </label>
                                <select
                                    value={sessionId}
                                    onChange={(event) =>
                                        setSessionId(event.target.value)
                                    }
                                    disabled={isLoadingSessions}
                                    className="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm focus:ring-2 focus:ring-[#1E3A8A] focus:outline-none disabled:opacity-60"
                                >
                                    {isLoadingSessions && (
                                        <option value="">Memuat sesi...</option>
                                    )}
                                    {!isLoadingSessions &&
                                        sessions.length === 0 && (
                                            <option value="">
                                                Tidak ada sesi tersedia
                                            </option>
                                        )}
                                    {sessions.map((session) => (
                                        <option
                                            key={session.session_id}
                                            value={session.session_id}
                                        >
                                            {formatDate(session.session_date)}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="mb-4 flex gap-3">
                                {(['IZIN', 'SAKIT'] as const).map((status) => (
                                    <button
                                        key={status}
                                        type="button"
                                        onClick={() =>
                                            setRequestedStatus(status)
                                        }
                                        className={`flex-1 rounded-xl py-3 text-sm font-semibold transition-colors ${
                                            requestedStatus === status
                                                ? 'bg-[#1E3A8A] text-white'
                                                : 'border border-gray-200 bg-white text-gray-600'
                                        }`}
                                    >
                                        {status === 'IZIN' ? 'Izin' : 'Sakit'}
                                    </button>
                                ))}
                            </div>

                            <div className="mb-6 flex min-h-[200px] flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                                <textarea
                                    value={reason}
                                    onChange={(event) =>
                                        setReason(event.target.value)
                                    }
                                    placeholder="Tulis alasan untuk izin......"
                                    rows={8}
                                    className="h-full w-full resize-none bg-transparent text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none"
                                />
                            </div>

                            {error && (
                                <p className="mb-4 text-xs font-medium text-rose-600">
                                    {error}
                                </p>
                            )}

                            <div className="mb-6 shrink-0 rounded-xl border border-gray-800 p-4">
                                <p className="text-center text-[11px] leading-tight font-medium text-black">
                                    Izin harus disertai surat yang sudah
                                    dikirimkan ke sekolah.
                                </p>
                            </div>
                        </div>

                        <button
                            type="submit"
                            disabled={isSubmitting}
                            className="w-full shrink-0 rounded-xl bg-[#254BA0] py-4 font-semibold text-white shadow-md transition-transform active:scale-95 disabled:opacity-60"
                        >
                            {isSubmitting ? 'Mengirim...' : 'Buat Izin'}
                        </button>
                    </form>
                )}
            </div>
        </>
    );
}
