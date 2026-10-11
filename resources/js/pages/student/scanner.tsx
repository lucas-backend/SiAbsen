import { useCallback, useEffect, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { BrowserQRCodeReader, type IScannerControls } from '@zxing/browser';
import {
    Camera,
    ChevronLeft,
    Check,
    Loader2,
    MapPin,
    QrCode,
    RefreshCw,
} from 'lucide-react';
import OutOfRangeAlertModal from '@/components/modal-peringatan';
import { useGeolocation } from '@/hooks/use-geolocation';
import { ApiError, apiRequest } from '@/lib/api';
import { decodeAttendanceQr } from '@/lib/attendance-qr';
import { dashboard } from '@/routes';
import attendance from '@/routes/attendance';
import type { AttendanceRecord } from '@/types';

type ScannerView = 'scan' | 'success' | 'failed';

interface AttendanceStatusProps {
    initialView?: ScannerView;
}

export default function AttendanceScanner({
    initialView = 'scan',
}: AttendanceStatusProps) {
    const [view, setView] = useState<ScannerView>(initialView);
    const [result, setResult] = useState<AttendanceRecord | null>(null);
    const [failMessage, setFailMessage] = useState('');
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isScanning, setIsScanning] = useState(false);
    const [restartKey, setRestartKey] = useState(0);
    const [showAlertModal, setShowAlertModal] = useState(false);

    const videoRef = useRef<HTMLVideoElement | null>(null);
    const controlsRef = useRef<IScannerControls | null>(null);
    const readerRef = useRef<BrowserQRCodeReader | null>(null);
    const decodedRef = useRef(false);

    const {
        getPosition,
        error: locationError,
        status: locationStatus,
    } = useGeolocation();

    const stopScanner = useCallback(() => {
        controlsRef.current?.stop();
        controlsRef.current = null;
        setIsScanning(false);
    }, []);

    const submitScan = useCallback(
        async (text: string) => {
            stopScanner();
            setIsSubmitting(true);

            const parsed = decodeAttendanceQr(text);

            if (!parsed) {
                setFailMessage(
                    'QR tidak dikenali. Pastikan Anda memindai QR kelas yang valid.',
                );
                setView('failed');
                setIsSubmitting(false);

                return;
            }

            const position = await getPosition();

            try {
                const response = await apiRequest<{ data: AttendanceRecord }>(
                    attendance.scan.store({
                        attendanceSession: parsed.sessionId,
                    }).url,
                    {
                        method: 'POST',
                        body: {
                            qr_payload: parsed.qrPayload,
                            latitude: position?.latitude ?? null,
                            longitude: position?.longitude ?? null,
                        },
                    },
                );

                setResult(response.data);
                setView('success');
            } catch (error) {
                if (error instanceof ApiError) {
                    setFailMessage(
                        error.firstError('scan') ??
                            error.firstError('qr_payload') ??
                            error.message,
                    );
                } else {
                    setFailMessage(
                        'Terjadi kesalahan saat memproses absensi. Coba lagi.',
                    );
                }

                setView('failed');
            } finally {
                setIsSubmitting(false);
            }
        },
        [getPosition, stopScanner],
    );

    useEffect(() => {
        if (view !== 'scan') {
            return;
        }

        let cancelled = false;

        setCameraError(null);
        decodedRef.current = false;

        const start = async () => {
            if (!videoRef.current || controlsRef.current) {
                return;
            }

            try {
                const reader = readerRef.current ?? new BrowserQRCodeReader();

                readerRef.current = reader;

                const controls = await reader.decodeFromVideoDevice(
                    undefined,
                    videoRef.current,
                    (scanResult) => {
                        if (scanResult && !decodedRef.current) {
                            decodedRef.current = true;
                            void submitScan(scanResult.getText());
                        }
                    },
                );

                if (cancelled) {
                    controls.stop();

                    return;
                }

                controlsRef.current = controls;
                setIsScanning(true);
            } catch (error) {
                if (cancelled) {
                    return;
                }

                const denied =
                    error instanceof DOMException &&
                    (error.name === 'NotAllowedError' ||
                        error.name === 'PermissionDeniedError');

                setCameraError(
                    denied
                        ? 'Izin kamera ditolak. Aktifkan akses kamera lalu coba lagi.'
                        : 'Kamera tidak dapat diakses. Gunakan HTTPS dan pastikan perangkat memiliki kamera.',
                );
            }
        };

        void start();

        return () => {
            cancelled = true;
            stopScanner();
        };
    }, [view, restartKey, submitScan, stopScanner]);

    if (view === 'scan') {
        return (
            <>
                <Head title="Scan QR Absen - SiAbsen" />
                <div className="relative mx-auto flex min-h-screen max-w-md flex-col justify-between bg-[#4a4a4a] shadow-2xl">
                    <div className="flex items-center gap-4 px-6 pt-12 text-white">
                        <Link
                            href={dashboard()}
                            className="text-white hover:opacity-80"
                        >
                            <ChevronLeft size={28} />
                        </Link>
                        <h1 className="text-lg font-medium">Scan QR Absen</h1>
                    </div>

                    <div className="flex flex-1 flex-col items-center justify-center px-6">
                        <div className="relative flex h-64 w-64 flex-col items-center justify-center overflow-hidden rounded-2xl border-2 border-white/60 bg-black/40 p-1 text-white">
                            <div className="absolute top-2 left-2 z-10 h-6 w-6 rounded-tl-sm border-t-4 border-l-4 border-teal-400" />
                            <div className="absolute top-2 right-2 z-10 h-6 w-6 rounded-tr-sm border-t-4 border-r-4 border-teal-400" />
                            <div className="absolute bottom-2 left-2 z-10 h-6 w-6 rounded-bl-sm border-b-4 border-l-4 border-teal-400" />
                            <div className="absolute right-2 bottom-2 z-10 h-6 w-6 rounded-br-sm border-r-4 border-b-4 border-teal-400" />

                            <video
                                ref={videoRef}
                                className="h-full w-full rounded-xl object-cover"
                                muted
                                playsInline
                            />

                            {!isScanning && !cameraError && (
                                <div className="absolute inset-0 flex flex-col items-center justify-center">
                                    <Loader2
                                        size={40}
                                        className="animate-spin text-white/70"
                                    />
                                    <p className="mt-2 text-xs text-white/70">
                                        Menyalakan kamera...
                                    </p>
                                </div>
                            )}

                            {cameraError && (
                                <div className="absolute inset-0 flex flex-col items-center justify-center px-4 text-center">
                                    <QrCode
                                        size={48}
                                        className="text-white/40"
                                    />
                                    <p className="mt-3 text-xs text-white/80">
                                        {cameraError}
                                    </p>
                                </div>
                            )}

                            {isSubmitting && (
                                <div className="absolute inset-0 z-20 flex flex-col items-center justify-center bg-black/60">
                                    <Loader2
                                        size={40}
                                        className="animate-spin text-teal-400"
                                    />
                                    <p className="mt-2 text-xs text-white">
                                        Memproses absensi...
                                    </p>
                                </div>
                            )}
                        </div>

                        {cameraError && (
                            <button
                                type="button"
                                onClick={() => setRestartKey((key) => key + 1)}
                                className="mt-6 flex items-center gap-2 rounded-full border border-white/40 bg-white/10 px-4 py-2 text-xs font-medium text-white transition-colors hover:bg-white/20"
                            >
                                <RefreshCw size={14} /> Coba Nyalakan Kamera
                            </button>
                        )}

                        {!cameraError && (
                            <p className="mt-6 text-center text-xs text-white/60">
                                Arahkan kamera ke QR Code kelas
                            </p>
                        )}
                    </div>

                    <div className="rounded-t-3xl bg-white p-6 pb-10">
                        <div className="mb-4 flex items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white py-4 text-gray-800 shadow-xs">
                            <Camera size={20} /> Scan QR Absensi
                        </div>
                        <p className="px-4 text-center text-xs leading-relaxed font-bold text-black">
                            harap untuk lokasi diaktifkan agar bisa diakses
                            secara real-time
                        </p>
                        {locationStatus === 'denied' && locationError && (
                            <p className="mt-3 px-2 text-center text-[11px] font-medium text-rose-600">
                                {locationError}
                            </p>
                        )}
                    </div>
                </div>

                <OutOfRangeAlertModal
                    isOpen={showAlertModal}
                    onClose={() => setShowAlertModal(false)}
                />
            </>
        );
    }

    const isSuccess = view === 'success';
    const isOutOfRadius =
        result?.location_status === 'DI_LUAR_RADIUS' ||
        result?.location_status === 'TIDAK_TERVERIFIKASI';

    const successTitle =
        result?.status === 'TERLAMBAT' ? 'Absen Terlambat' : 'Absen Berhasil';

    const successMessage = () => {
        if (!result) {
            return 'Kehadiran Anda telah tercatat.';
        }

        if (result.location_status === 'DI_LUAR_RADIUS') {
            const distance = result.distance_meters ?? 0;

            return `Kehadiran tercatat, namun Anda terdeteksi ±${distance} m di luar radius kelas.`;
        }

        if (result.location_status === 'TIDAK_TERVERIFIKASI') {
            return 'Kehadiran tercatat, namun lokasi tidak terverifikasi. Aktifkan GPS untuk verifikasi.';
        }

        if (result.status === 'TERLAMBAT') {
            return `Kehadiran tercatat dengan keterlambatan ${result.late_minutes ?? 0} menit.`;
        }

        return 'Kehadiran Anda telah tercatat dan diverifikasi dengan GPS.';
    };

    return (
        <>
            <Head
                title={
                    isSuccess
                        ? 'Absen Berhasil - SiAbsen'
                        : 'Absen Gagal - SiAbsen'
                }
            />
            <div className="relative mx-auto flex min-h-screen max-w-md flex-col items-center justify-center bg-white px-6 text-center shadow-2xl">
                <div className="flex w-full flex-1 flex-col items-center justify-center">
                    {isSuccess ? (
                        <div className="mb-8 text-[#1E3A8A]">
                            <Check size={120} strokeWidth={1} />
                        </div>
                    ) : (
                        <div className="mb-8 flex h-32 w-28 flex-col items-center justify-center rounded-t-full rounded-b-full bg-[#1E3A8A] shadow-lg">
                            <div className="mb-2 h-12 w-4 rounded-sm bg-white" />
                            <div className="h-4 w-4 rounded-sm bg-white" />
                        </div>
                    )}

                    <h1 className="mb-4 text-4xl font-bold tracking-tight text-black">
                        {isSuccess ? successTitle : 'Absen Gagal'}
                    </h1>
                    <p className="mb-8 px-2 text-sm font-semibold text-black">
                        {isSuccess ? successMessage() : failMessage}
                    </p>

                    {!isSuccess && (
                        <div className="mb-8 w-full rounded-xl border border-gray-800 p-4">
                            <p className="text-xs font-medium text-black">
                                Percobaan absensi ini telah dicatat. Jika Anda
                                yakin berada di lokasi yang benar, hubungi guru
                                Anda.
                            </p>
                        </div>
                    )}

                    {isSuccess && isOutOfRadius && (
                        <button
                            type="button"
                            onClick={() => setShowAlertModal(true)}
                            className="mb-4 flex items-center gap-1 text-xs font-semibold text-[#1E3A8A] underline"
                        >
                            <MapPin size={14} /> Lihat Detail Lokasi
                        </button>
                    )}

                    {!isSuccess && (
                        <button
                            type="button"
                            onClick={() => setShowAlertModal(true)}
                            className="mb-4 text-xs font-semibold text-[#1E3A8A] underline"
                        >
                            Lihat Peringatan Geofence
                        </button>
                    )}
                </div>

                <div className="w-full space-y-3 pb-10">
                    {!isSuccess && (
                        <button
                            type="button"
                            onClick={() => {
                                setResult(null);
                                setFailMessage('');
                                setRestartKey((key) => key + 1);
                                setView('scan');
                            }}
                            className="w-full rounded-xl border border-[#1E3A8A] py-3.5 font-semibold text-[#1E3A8A] transition-transform active:scale-95"
                        >
                            Coba Scan Lagi
                        </button>
                    )}
                    <Link
                        href={dashboard()}
                        className="block w-full rounded-xl bg-[#1E3A8A] py-4 text-center font-semibold text-white shadow-md transition-transform active:scale-95"
                    >
                        Kembali ke Beranda
                    </Link>
                </div>

                <OutOfRangeAlertModal
                    isOpen={showAlertModal}
                    onClose={() => setShowAlertModal(false)}
                    message={
                        isOutOfRadius && result
                            ? `Kehadiran tercatat namun lokasi Anda berada ${result.distance_meters ?? 0} meter dari titik kelas.`
                            : undefined
                    }
                />
            </div>
        </>
    );
}
