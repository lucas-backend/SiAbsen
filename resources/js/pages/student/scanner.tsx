import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Camera, ChevronLeft, Check, QrCode } from 'lucide-react';
import OutOfRangeAlertModal from '@/components/modal-peringatan';

interface AttendanceStatusProps {
    initialView?: 'scan' | 'success' | 'failed';
}

export default function AttendanceScanner({ initialView = 'scan' }: AttendanceStatusProps) {
    const [view, setView] = useState<'scan' | 'success' | 'failed'>(initialView);
    const [showAlertModal, setShowAlertModal] = useState(false);

    const handleScanClick = () => {
        // Simulasi scan: 70% berhasil, 30% gagal (geofence)
        const simulatedSuccess = Math.random() > 0.3;
        if (simulatedSuccess) {
            setView('success');
        } else {
            setView('failed');
            setShowAlertModal(true);
        }
    };

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

                    {/* Mockup Frame QR Viewfinder */}
                    <div className="flex-1 flex flex-col justify-center items-center px-6">
                        <div className="relative w-64 h-64 bg-black/40 border-2 border-white/60 rounded-2xl p-4 flex flex-col items-center justify-center text-white">
                            {/* Scanning corners */}
                            <div className="absolute top-2 left-2 w-6 h-6 border-t-4 border-l-4 border-teal-400 rounded-tl-sm"></div>
                            <div className="absolute top-2 right-2 w-6 h-6 border-t-4 border-r-4 border-teal-400 rounded-tr-sm"></div>
                            <div className="absolute bottom-2 left-2 w-6 h-6 border-b-4 border-l-4 border-teal-400 rounded-bl-sm"></div>
                            <div className="absolute bottom-2 right-2 w-6 h-6 border-b-4 border-r-4 border-teal-400 rounded-br-sm"></div>

                            <QrCode size={120} className="text-white/40 animate-pulse" />
                            <p className="text-xs text-white/70 mt-3 text-center">Arahkan kamera ke QR Code kelas</p>
                        </div>

                        {/* Switch state tester buttons */}
                        <div className="flex gap-2 mt-6">
                            <button
                                type="button"
                                onClick={() => setView('success')}
                                className="text-[11px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-3 py-1 rounded-full hover:bg-emerald-500/30 transition-colors"
                            >
                                Test Berhasil
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    setView('failed');
                                    setShowAlertModal(true);
                                }}
                                className="text-[11px] bg-rose-500/20 text-rose-300 border border-rose-500/40 px-3 py-1 rounded-full hover:bg-rose-500/30 transition-colors"
                            >
                                Test Gagal
                            </button>
                        </div>
                    </div>

                    <div className="bg-white rounded-t-3xl p-6 pb-10">
                        <button
                            type="button"
                            onClick={handleScanClick}
                            className="w-full border border-gray-300 bg-white hover:bg-gray-50 text-gray-800 font-semibold py-4 rounded-xl flex items-center justify-center gap-2 mb-4 active:scale-95 transition-transform shadow-xs"
                        >
                            <Camera size={20} /> Scan QR Absensi
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
                    {/* Ikon */}
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

                    {/* Teks */}
                    <h1 className="text-4xl font-bold text-black tracking-tight mb-4">
                        {isSuccess ? 'Absen Berhasil' : 'Absen Gagal'}
                    </h1>
                    <p className="text-sm font-semibold text-black px-2 mb-8">
                        {isSuccess
                            ? 'Kehadiran Anda telah tercatat dan diverifikasi dengan GPS.'
                            : 'Anda berada di luar radius geofence yang diizinkan. Pindah ke lokasi kelas dan coba lagi.'}
                    </p>

                    {/* Kotak Peringatan (Hanya Gagal) */}
                    {!isSuccess && (
                        <div className="border border-gray-800 rounded-xl p-4 w-full mb-8">
                            <p className="text-xs font-medium text-black">
                                Percobaan absensi ini telah dicatat. Jika Anda yakin berada di lokasi yang benar, hubungi guru Anda.
                            </p>
                        </div>
                    )}

                    {!isSuccess && (
                        <button
                            type="button"
                            onClick={() => setShowAlertModal(true)}
                            className="text-xs text-[#1E3A8A] font-semibold underline mb-4"
                        >
                            Lihat Peringatan Geofence
                        </button>
                    )}
                </div>

                {/* Tombol Bawah */}
                <div className="w-full pb-10 space-y-3">
                    {!isSuccess && (
                        <button
                            type="button"
                            onClick={() => setView('scan')}
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
                />
            </div>
        </>
    );
}
