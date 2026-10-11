import React from 'react';
import { Camera, ChevronLeft, Check, MapPin } from 'lucide-react';

interface AttendanceStatusProps {
    view: 'scan' | 'success' | 'failed';
}

const AttendanceStatus: React.FC<AttendanceStatusProps> = ({ view }) => {
    if (view === 'scan') {
        return (
            <div className="min-h-screen bg-[#4a4a4a] relative flex flex-col justify-between">
                <div className="pt-12 px-6 flex items-center text-white gap-4">
                    <ChevronLeft size={28} />
                    <h1 className="font-medium text-lg">Scan QR Absen</h1>
                </div>

                {/* Mockup Frame QR */}
                <div className="flex-1 flex justify-center items-center">
                    <div className="w-64 h-64 bg-white p-2">
                        <img src="/api/placeholder/240/240" alt="QR Frame" className="w-full h-full object-cover grayscale opacity-80" />
                    </div>
                </div>

                <div className="bg-white rounded-t-3xl p-6 pb-10">
                    <button className="w-full border border-gray-300 text-gray-600 font-semibold py-4 rounded-xl flex items-center justify-center gap-2 mb-4">
                        <Camera size={20} /> Scan QR Absensi
                    </button>
                    <p className="text-xs font-bold text-center text-black px-4">
                        harap untuk lokasi diaktifkan agar bisa diakses secara real-time
                    </p>
                </div>
            </div>
        );
    }

    const isSuccess = view === 'success';

    return (
        <div className="min-h-screen bg-white flex flex-col items-center justify-center px-6 text-center relative">
            <div className="flex-1 flex flex-col items-center justify-center">
                {/* Ikon */}
                {isSuccess ? (
                    <div className="mb-8 text-[#1E3A8A]">
                        <Check size={120} strokeWidth={1} />
                    </div>
                ) : (
                    <div className="mb-8 w-28 h-32 bg-[#1E3A8A] rounded-t-full rounded-b-full flex flex-col items-center justify-center clip-path-pin">
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
            </div>

            {/* Tombol Bawah */}
            <div className="w-full pb-10">
                <button className="w-full bg-[#1E3A8A] text-white font-semibold py-4 rounded-xl">
                    Kembali ke Beranda
                </button>
            </div>
        </div>
    );
};

export default AttendanceStatus;