import { useCallback, useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import { Check, Loader2, X } from 'lucide-react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ApiError, apiRequest } from '@/lib/api';
import attendance from '@/routes/attendance';
import type { AttendanceRequestItem, AttendanceRequestStatus } from '@/types';

type StatusFilter = 'ALL' | AttendanceRequestStatus;

const FILTERS: { key: StatusFilter; label: string }[] = [
    { key: 'ALL', label: 'Semua' },
    { key: 'PENDING', label: 'Menunggu' },
    { key: 'APPROVED', label: 'Disetujui' },
    { key: 'REJECTED', label: 'Ditolak' },
];

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function requestBadge(status: AttendanceRequestStatus): string {
    switch (status) {
        case 'APPROVED':
            return 'bg-emerald-100 text-emerald-700';
        case 'REJECTED':
            return 'bg-rose-100 text-rose-700';
        default:
            return 'bg-amber-100 text-amber-700';
    }
}

export default function AttendanceRequestsPage() {
    const [requests, setRequests] = useState<AttendanceRequestItem[]>([]);
    const [filter, setFilter] = useState<StatusFilter>('PENDING');
    const [isLoading, setIsLoading] = useState(true);
    const [reviewingId, setReviewingId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);

    const load = useCallback(async () => {
        setIsLoading(true);

        try {
            const response = await apiRequest<{
                data: AttendanceRequestItem[];
            }>(
                attendance.requests.index.url({
                    query: filter === 'ALL' ? {} : { status: filter },
                }),
            );

            setRequests(response.data);
            setError(null);
        } catch {
            setError('Gagal memuat daftar pengajuan izin.');
        } finally {
            setIsLoading(false);
        }
    }, [filter]);

    useEffect(() => {
        void load();
    }, [load]);

    const handleReview = async (
        item: AttendanceRequestItem,
        status: 'APPROVED' | 'REJECTED',
    ) => {
        setReviewingId(item.id);

        try {
            const response = await apiRequest<{ data: AttendanceRequestItem }>(
                attendance.requests.review({
                    attendanceRequest: item.id,
                }).url,
                { method: 'PATCH', body: { status } },
            );

            setRequests((previous) => {
                const updated = previous.map((request) =>
                    request.id === item.id ? response.data : request,
                );

                if (filter === 'PENDING') {
                    return updated.filter(
                        (request) => request.status === 'PENDING',
                    );
                }

                return updated;
            });

            toast.success(
                status === 'APPROVED'
                    ? 'Pengajuan disetujui.'
                    : 'Pengajuan ditolak.',
            );
        } catch (requestError) {
            const message =
                requestError instanceof ApiError
                    ? (requestError.firstError('status') ??
                      requestError.message)
                    : 'Gagal meninjau pengajuan.';

            toast.error(message);
        } finally {
            setReviewingId(null);
        }
    };

    const counts = {
        ALL: requests.length,
        PENDING: requests.filter((request) => request.status === 'PENDING')
            .length,
        APPROVED: requests.filter((request) => request.status === 'APPROVED')
            .length,
        REJECTED: requests.filter((request) => request.status === 'REJECTED')
            .length,
    };

    return (
        <>
            <Head title="Persetujuan Izin - SiAbsen" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-bold text-[#1E3A8A]">
                        Persetujuan Izin & Sakit
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Tinjau pengajuan izin/sakit siswa. Menyetujui pengajuan
                        akan menulis status kehadiran IZIN/SAKIT.
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    {FILTERS.map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => setFilter(item.key)}
                            className={`flex items-center gap-2 rounded-full px-4 py-2 text-xs font-bold transition-colors ${
                                filter === item.key
                                    ? 'bg-[#1E3A8A] text-white'
                                    : 'border border-gray-300 text-gray-600 hover:bg-gray-50'
                            }`}
                        >
                            {item.label}
                            <span className="rounded-full bg-black/10 px-1.5 py-0.5 text-[10px]">
                                {counts[item.key]}
                            </span>
                        </button>
                    ))}
                </div>

                {isLoading ? (
                    <div className="text-muted-foreground flex items-center justify-center gap-2 py-16 text-sm">
                        <Loader2 className="animate-spin" /> Memuat pengajuan...
                    </div>
                ) : error ? (
                    <p className="py-16 text-center text-sm text-rose-600">
                        {error}
                    </p>
                ) : requests.length === 0 ? (
                    <p className="text-muted-foreground py-16 text-center text-sm">
                        Tidak ada pengajuan untuk filter ini.
                    </p>
                ) : (
                    <div className="space-y-3">
                        {requests.map((item) => (
                            <div
                                key={item.id}
                                className="bg-card flex flex-col gap-3 rounded-xl border p-4 shadow-sm md:flex-row md:items-center md:justify-between"
                            >
                                <div className="space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="font-bold text-gray-900">
                                            {item.student_name ?? 'Siswa'}
                                        </h2>
                                        <Badge
                                            className={
                                                item.requested_status === 'IZIN'
                                                    ? 'bg-sky-100 text-sky-700'
                                                    : 'bg-violet-100 text-violet-700'
                                            }
                                        >
                                            {item.requested_status_label}
                                        </Badge>
                                        <Badge
                                            className={requestBadge(
                                                item.status,
                                            )}
                                        >
                                            {item.status_label}
                                        </Badge>
                                    </div>
                                    <p className="text-muted-foreground text-xs">
                                        {item.class_name ?? '-'} ·{' '}
                                        {formatDate(item.session_date)}
                                    </p>
                                    {item.reason && (
                                        <p className="text-sm text-gray-700">
                                            {item.reason}
                                        </p>
                                    )}
                                    {item.reviewer_name && (
                                        <p className="text-muted-foreground text-xs">
                                            Ditinjau oleh {item.reviewer_name}
                                        </p>
                                    )}
                                </div>

                                {item.status === 'PENDING' && (
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            disabled={reviewingId === item.id}
                                            onClick={() =>
                                                void handleReview(
                                                    item,
                                                    'REJECTED',
                                                )
                                            }
                                        >
                                            <X /> Tolak
                                        </Button>
                                        <Button
                                            disabled={reviewingId === item.id}
                                            onClick={() =>
                                                void handleReview(
                                                    item,
                                                    'APPROVED',
                                                )
                                            }
                                        >
                                            <Check /> Setujui
                                        </Button>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
