import React from 'react';
import { Camera, ChevronLeft, Check } from 'lucide-react';

interface AttendanceStatusProps {
    view: 'scan' | 'success' | 'failed';
}

const AttendanceStatus: React.FC<AttendanceStatusProps> = ({ view }) => {
    if (view === 'scan') {
        return (
            <div className="relative flex min-h-screen flex-col justify-between bg-[#4a4a4a]">
                <div className="flex items-center gap-4 px-6 pt-12 text-white">
                    <ChevronLeft size={28} />
                    <h1 className="text-lg font-medium">Scan QR Absen</h1>
                </div>

                {/* Mockup Frame QR */}
                <div className="flex flex-1 items-center justify-center">
                    <div className="h-64 w-64 bg-white p-2">
                        <img
                            src="/api/placeholder/240/240"
                            alt="QR Frame"
                            className="h-full w-full object-cover opacity-80 grayscale"
                        />
                    </div>
                </div>

                <div className="rounded-t-3xl bg-white p-6 pb-10">
                    <button className="mb-4 flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 py-4 font-semibold text-gray-600">
                        <Camera size={20} /> Scan QR Absensi
                    </button>
                    <p className="px-4 text-center text-xs font-bold text-black">
                        harap untuk lokasi diaktifkan agar bisa diakses secara
                        real-time
                    </p>
                </div>
            </div>
        );
    }

    const isSuccess = view === 'success';

    return (
        <div className="relative flex min-h-screen flex-col items-center justify-center bg-white px-6 text-center">
            <div className="flex flex-1 flex-col items-center justify-center">
                {/* Ikon */}
                {isSuccess ? (
                    <div className="mb-8 text-[#1E3A8A]">
                        <Check size={120} strokeWidth={1} />
                    </div>
                ) : (
                    <div className="clip-path-pin mb-8 flex h-32 w-28 flex-col items-center justify-center rounded-t-full rounded-b-full bg-[#1E3A8A]">
                        <div className="mb-2 h-12 w-4 rounded-sm bg-white"></div>
                        <div className="h-4 w-4 rounded-sm bg-white"></div>
                    </div>
                )}

                {/* Teks */}
                <h1 className="mb-4 text-4xl font-bold tracking-tight text-black">
                    {isSuccess ? 'Absen Berhasil' : 'Absen Gagal'}
                </h1>
                <p className="mb-8 px-2 text-sm font-semibold text-black">
                    {isSuccess
                        ? 'Kehadiran Anda telah tercatat dan diverifikasi dengan GPS.'
                        : 'Anda berada di luar radius geofence yang diizinkan. Pindah ke lokasi kelas dan coba lagi.'}
                </p>

                {/* Kotak Peringatan (Hanya Gagal) */}
                {!isSuccess && (
                    <div className="mb-8 w-full rounded-xl border border-gray-800 p-4">
                        <p className="text-xs font-medium text-black">
                            Percobaan absensi ini telah dicatat. Jika Anda yakin
                            berada di lokasi yang benar, hubungi guru Anda.
                        </p>
                    </div>
                )}
            </div>

            {/* Tombol Bawah */}
            <div className="w-full pb-10">
                <button className="w-full rounded-xl bg-[#1E3A8A] py-4 font-semibold text-white">
                    Kembali ke Beranda
                </button>
            </div>
        </div>
    );
};

export default AttendanceStatus;
