import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Camera, ChevronLeft, Check, QrCode, AlertCircle, Loader2, MapPin, RotateCcw } from 'lucide-react';
import { Html5Qrcode } from 'html5-qrcode';
import OutOfRangeAlertModal from '@/components/modal-peringatan';

interface AttendanceStatusProps {
    initialView?: 'scan' | 'success' | 'failed';
}

interface ScanRecordData {
    status: string;
    location_status: string;
    distance_meters?: number;
    scanned_at?: string;
    late_minutes?: number;
}

function getXsrfToken(): string | null {
    if (typeof document === 'undefined') return null;
    const cookie = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.split('=')[1]) : null;
}

export default function AttendanceScanner({ initialView = 'scan' }: AttendanceStatusProps) {
    const [view, setView] = useState<'scan' | 'success' | 'failed'>(initialView);
    const [showAlertModal, setShowAlertModal] = useState(false);

    // State lokasi & kamera
    const [coordinates, setCoordinates] = useState<{ latitude: number; longitude: number } | null>(null);
    const [isAcquiringGps, setIsAcquiringGps] = useState(false);
    const [gpsError, setGpsError] = useState<string | null>(null);
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [isCameraActive, setIsCameraActive] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // State hasil scan
    const [scanResult, setScanResult] = useState<ScanRecordData | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [errorTitle, setErrorTitle] = useState<string>('Absen Gagal');

    // Ref untuk kontrol kamera & pencegahan scan paralel
    const scannerRef = useRef<Html5Qrcode | null>(null);
    const isSubmittingRef = useRef(false);
    const coordsRef = useRef<{ latitude: number; longitude: number } | null>(null);

    // Sinkronisasi ref koordinat
    useEffect(() => {
        coordsRef.current = coordinates;
    }, [coordinates]);

    // Hentikan kamera dengan aman
    const stopCamera = useCallback(async () => {
        if (scannerRef.current) {
            try {
                if (scannerRef.current.isScanning) {
                    await scannerRef.current.stop();
                }
                scannerRef.current.clear();
            } catch (err) {
                console.error('Error stopping camera:', err);
            }
            scannerRef.current = null;
        }
        setIsCameraActive(false);
    }, []);

    // Bersihkan resource saat komponen unmount
    useEffect(() => {
        return () => {
            if (scannerRef.current?.isScanning) {
                scannerRef.current.stop().catch(() => {}).finally(() => {
                    scannerRef.current?.clear();
                });
            }
        };
    }, []);

    // Kirim data scan ke endpoint backend POST /student/scan
    const submitScan = useCallback(async (token: string, lat: number, lng: number) => {
        if (isSubmittingRef.current) return;
        isSubmittingRef.current = true;
        setIsSubmitting(true);

        // Hentikan pemindaian kamera setelah membaca QR
        await stopCamera();

        try {
            const xsrfToken = getXsrfToken();
            const response = await fetch('/student/scan', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
                },
                body: JSON.stringify({
                    qr_token: token,
                    latitude: lat,
                    longitude: lng,
                }),
            });

            const data = await response.json().catch(() => null);

            if (response.status === 201) {
                setScanResult(data?.data);
                setView('success');
            } else if (response.status === 409) {
                setErrorTitle('Sudah Melakukan Absensi');
                setErrorMessage(data?.message || 'Anda sudah melakukan absensi pada sesi ini.');
                setView('failed');
            } else if (response.status === 403) {
                setErrorTitle('Akses Ditolak');
                setErrorMessage(data?.message || 'Anda tidak terdaftar sebagai siswa aktif di kelas ini.');
                setView('failed');
            } else if (response.status === 422) {
                setErrorTitle('Absen Gagal');
                const validationMsg = data?.errors?.qr_token?.[0]
                    || data?.errors?.latitude?.[0]
                    || data?.errors?.longitude?.[0]
                    || data?.errors?.session?.[0]
                    || data?.errors?.gps?.[0]
                    || data?.errors?.school?.[0]
                    || data?.message
                    || 'Token QR atau data absensi tidak valid.';
                setErrorMessage(validationMsg);
                setView('failed');
            } else {
                setErrorTitle('Gagal Memproses Absensi');
                setErrorMessage(data?.message || 'Terjadi kesalahan pada server saat memproses absensi.');
                setView('failed');
            }
        } catch {
            setErrorTitle('Gangguan Jaringan');
            setErrorMessage('Gagal menghubungi server. Periksa koneksi internet Anda dan coba lagi.');
            setView('failed');
        } finally {
            setIsSubmitting(false);
            isSubmittingRef.current = false;
        }
    }, [stopCamera]);

    // Memulai pemindaian kamera
    const startCamera = useCallback(async (lat: number, lng: number) => {
        setCameraError(null);

        try {
            await stopCamera();

            const readerElement = document.getElementById('reader');
            if (!readerElement) {
                setCameraError('Komponen viewfinder scanner tidak ditemukan.');
                return;
            }

            const html5QrCode = new Html5Qrcode('reader');
            scannerRef.current = html5QrCode;

            await html5QrCode.start(
                { facingMode: 'environment' },
                {
                    fps: 10,
                    qrbox: { width: 220, height: 220 },
                    aspectRatio: 1.0,
                },
                (decodedText) => {
                    // Berhasil decode QR
                    if (!isSubmittingRef.current) {
                        submitScan(decodedText, lat, lng);
                    }
                },
                () => {
                    // Abaikan frame kegagalan decode per detik
                }
            );

            setIsCameraActive(true);
        } catch (err: unknown) {
            console.error('Error starting camera:', err);
            setIsCameraActive(false);
            const errStr = String(err);
            if (errStr.includes('NotAllowedError') || errStr.includes('Permission')) {
                setCameraError('Izin akses kamera ditolak. Harap izinkan akses kamera pada browser Anda.');
            } else if (errStr.includes('NotFoundError')) {
                setCameraError('Kamera tidak ditemukan pada perangkat Anda.');
            } else {
                setCameraError('Gagal mengaktifkan kamera browser. Pastikan kamera tidak digunakan aplikasi lain.');
            }
        }
    }, [stopCamera, submitScan]);

    // Tombol aksi siswa untuk meminta izin GPS dan memulai pemindaian kamera
    const handleStartScan = useCallback(() => {
        setGpsError(null);
        setCameraError(null);

        if (!navigator.geolocation) {
            setGpsError('Perangkat atau browser Anda tidak mendukung fitur lokasi (Geolocation).');
            return;
        }

        setIsAcquiringGps(true);

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                setCoordinates({ latitude: lat, longitude: lng });
                setIsAcquiringGps(false);

                // Mulai kamera setelah lokasi berhasil didapatkan
                await startCamera(lat, lng);
            },
            (error) => {
                setIsAcquiringGps(false);
                let msg = 'Gagal mendeteksi lokasi GPS.';
                if (error.code === 1) {
                    msg = 'Izin lokasi (GPS) ditolak. Harap aktifkan izin lokasi di pengaturan browser Anda.';
                } else if (error.code === 2) {
                    msg = 'Lokasi GPS tidak tersedia. Pastikan fitur lokasi perangkat Anda telah aktif.';
                } else if (error.code === 3) {
                    msg = 'Waktu pengambilan lokasi GPS habis (timeout). Silakan coba lagi.';
                }
                setGpsError(msg);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0,
            }
        );
    }, [startCamera]);

    // Reset status saat ingin scan ulang
    const handleResetScan = useCallback(() => {
        setView('scan');
        setErrorMessage(null);
        setGpsError(null);
        setCameraError(null);
        setScanResult(null);
        setIsSubmitting(false);
        isSubmittingRef.current = false;
    }, []);

    if (view === 'scan') {
        return (
            <>
                <Head title="Scan QR Absen - EduPresence" />
                <div className="min-h-screen bg-[#4a4a4a] relative flex flex-col justify-between max-w-md mx-auto shadow-2xl">
                    <div className="pt-12 px-6 flex items-center text-white gap-4">
                        <Link href="/dashboard" className="text-white hover:opacity-80">
                            <ChevronLeft size={28} />
                        </Link>
                        <h1 className="font-medium text-lg">Scan QR Absen</h1>
                    </div>

                    {/* Viewfinder Area */}
                    <div className="flex-1 flex flex-col justify-center items-center px-6">
                        <div className="relative w-64 h-64 bg-black/60 border-2 border-white/60 rounded-2xl overflow-hidden flex flex-col items-center justify-center text-white shadow-inner">
                            {/* Container Html5Qrcode video */}
                            <div id="reader" className="w-full h-full object-cover [&_video]:w-full [&_video]:h-full [&_video]:object-cover" />

                            {/* Tampilan awal saat kamera belum diaktifkan */}
                            {!isCameraActive && !isAcquiringGps && !isSubmitting && (
                                <div className="absolute inset-0 flex flex-col items-center justify-center p-4 bg-black/70 text-center z-10 pointer-events-none">
                                    <QrCode size={90} className="text-white/60 animate-pulse mb-3" />
                                    <p className="text-xs text-white/80 font-medium">Arahkan kamera ke QR Code kelas</p>
                                </div>
                            )}

                            {/* Indikator Loading GPS / Kirim Absensi */}
                            {(isAcquiringGps || isSubmitting) && (
                                <div className="absolute inset-0 flex flex-col items-center justify-center p-4 bg-black/85 text-center z-20">
                                    <Loader2 size={44} className="text-teal-400 animate-spin mb-3" />
                                    <p className="text-xs text-white font-semibold leading-relaxed">
                                        {isSubmitting ? 'Mengirim dan memverifikasi absensi...' : 'Mengakses koordinat GPS...'}
                                    </p>
                                </div>
                            )}

                            {/* Scanning corners */}
                            <div className="absolute top-2 left-2 w-6 h-6 border-t-4 border-l-4 border-teal-400 rounded-tl-sm pointer-events-none z-10"></div>
                            <div className="absolute top-2 right-2 w-6 h-6 border-t-4 border-r-4 border-teal-400 rounded-tr-sm pointer-events-none z-10"></div>
                            <div className="absolute bottom-2 left-2 w-6 h-6 border-b-4 border-l-4 border-teal-400 rounded-bl-sm pointer-events-none z-10"></div>
                            <div className="absolute bottom-2 right-2 w-6 h-6 border-b-4 border-r-4 border-teal-400 rounded-br-sm pointer-events-none z-10"></div>
                        </div>

                        {/* Indikator Status GPS / Kamera */}
                        {coordinates && (
                            <div className="flex items-center gap-1.5 mt-4 bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-3 py-1 rounded-full text-[11px]">
                                <MapPin size={12} />
                                <span>GPS Aktif ({coordinates.latitude.toFixed(4)}, {coordinates.longitude.toFixed(4)})</span>
                            </div>
                        )}

                        {/* Pesan Error GPS atau Kamera */}
                        {(gpsError || cameraError) && (
                            <div className="mt-4 bg-rose-500/20 border border-rose-500/50 text-rose-200 rounded-xl p-3 text-xs flex flex-col items-center text-center max-w-[280px]">
                                <AlertCircle size={20} className="text-rose-400 mb-1" />
                                <p className="font-medium leading-relaxed">{gpsError || cameraError}</p>
                                <button
                                    type="button"
                                    onClick={handleStartScan}
                                    className="mt-2 text-[11px] bg-rose-500/40 hover:bg-rose-500/60 text-white px-3 py-1 rounded-lg transition"
                                >
                                    Coba Lagi
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Tombol Kontrol Bawah */}
                    <div className="bg-white rounded-t-3xl p-6 pb-10">
                        <button
                            type="button"
                            onClick={handleStartScan}
                            disabled={isAcquiringGps || isSubmitting}
                            className="w-full border border-gray-300 bg-white hover:bg-gray-50 text-gray-800 font-semibold py-4 rounded-xl flex items-center justify-center gap-2 mb-4 active:scale-95 transition-transform shadow-xs disabled:opacity-50"
                        >
                            {isAcquiringGps || isSubmitting ? (
                                <>
                                    <Loader2 size={20} className="animate-spin text-[#1E3A8A]" />
                                    <span>Memproses...</span>
                                </>
                            ) : isCameraActive ? (
                                <>
                                    <RotateCcw size={20} className="text-[#1E3A8A]" />
                                    <span>Scan Ulang Kamera</span>
                                </>
                            ) : (
                                <>
                                    <Camera size={20} className="text-[#1E3A8A]" />
                                    <span>Scan QR Absensi</span>
                                </>
                            )}
                        </button>
                        <p className="text-xs font-bold text-center text-black px-4 leading-relaxed">
                            harap untuk lokasi diaktifkan agar bisa diakses secara real-time
                        </p>
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

    return (
        <>
            <Head title={isSuccess ? "Absen Berhasil - EduPresence" : "Absen Gagal - EduPresence"} />
            <div className="min-h-screen bg-white flex flex-col items-center justify-center px-6 text-center relative max-w-md mx-auto shadow-2xl">
                <div className="flex-1 flex flex-col items-center justify-center w-full">
                    {/* Ikon Status */}
                    {isSuccess ? (
                        <div className="mb-8 text-[#1E3A8A]">
                            <Check size={120} strokeWidth={1} />
                        </div>
                    ) : (
                        <div className="mb-8 w-28 h-32 bg-[#1E3A8A] rounded-t-full rounded-b-full flex flex-col items-center justify-center shadow-lg">
                            <div className="w-4 h-12 bg-white rounded-sm mb-2"></div>
                            <div className="w-4 h-4 bg-white rounded-sm"></div>
                        </div>
                    )}

                    {/* Teks Judul */}
                    <h1 className="text-4xl font-bold text-black tracking-tight mb-4">
                        {isSuccess ? 'Absen Berhasil' : errorTitle}
                    </h1>

                    {/* Deskripsi Status */}
                    <p className="text-sm font-semibold text-black px-2 mb-6">
                        {isSuccess
                            ? (scanResult?.location_status === 'OUT_OF_RANGE'
                                ? `Kehadiran Anda telah tercatat (${scanResult?.distance_meters ?? 0}m dari sekolah). Menunggu peninjauan guru.`
                                : 'Kehadiran Anda telah tercatat dan diverifikasi dengan GPS dalam radius sekolah.')
                            : (errorMessage || 'Terjadi kesalahan saat memproses absensi. Silakan coba lagi.')}
                    </p>

                    {/* Catatan Khusus Out of Range (Tetap Hadir) */}
                    {isSuccess && scanResult?.location_status === 'OUT_OF_RANGE' && (
                        <div className="border border-amber-300 bg-amber-50 rounded-xl p-4 w-full mb-6 text-left">
                            <div className="flex items-center gap-2 mb-1">
                                <AlertCircle size={16} className="text-amber-600 shrink-0" />
                                <span className="text-xs font-bold text-amber-800">Catatan: Di Luar Radius Sekolah</span>
                            </div>
                            <p className="text-xs text-amber-700 leading-relaxed">
                                Status absensi Anda tetap <strong className="font-bold">HADIR</strong>. Lokasi Anda berada di luar radius ({scanResult?.distance_meters ?? 0} meter dari sekolah).
                            </p>
                        </div>
                    )}

                    {/* Kotak Peringatan untuk Kegagalan */}
                    {!isSuccess && (
                        <div className="border border-gray-800 rounded-xl p-4 w-full mb-8">
                            <p className="text-xs font-medium text-black">
                                {errorMessage || 'Percobaan absensi ini tidak berhasil. Jika Anda yakin berada di lokasi yang benar, hubungi guru Anda.'}
                            </p>
                        </div>
                    )}

                    {isSuccess && scanResult?.location_status === 'OUT_OF_RANGE' && (
                        <button
                            type="button"
                            onClick={() => setShowAlertModal(true)}
                            className="text-xs text-[#1E3A8A] font-semibold underline mb-4"
                        >
                            Lihat Peringatan Geofence
                        </button>
                    )}
                </div>

                {/* Tombol Navigasi Bawah */}
                <div className="w-full pb-10 space-y-3">
                    {!isSuccess && (
                        <button
                            type="button"
                            onClick={handleResetScan}
                            className="w-full border border-[#1E3A8A] text-[#1E3A8A] font-semibold py-3.5 rounded-xl active:scale-95 transition-transform"
                        >
                            Coba Scan Lagi
                        </button>
                    )}
                    <Link
                        href="/dashboard"
                        className="block w-full bg-[#1E3A8A] text-white font-semibold py-4 rounded-xl active:scale-95 transition-transform shadow-md text-center"
                    >
                        Kembali ke Beranda
                    </Link>
                </div>

                <OutOfRangeAlertModal
                    isOpen={showAlertModal}
                    onClose={() => setShowAlertModal(false)}
                    title="Informasi Geofence"
                    message={`Absensi Berhasil Dicatat (HADIR)\nJarak: ${scanResult?.distance_meters ?? 0} meter dari titik sekolah.\nLokasi berada di luar radius normal sekolah.`}
                />
            </div>
        </>
    );
}
