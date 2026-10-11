import React from 'react';
import { Camera } from 'lucide-react';

interface DashboardProps {
    role: 'student' | 'teacher';
}

const Dashboard: React.FC<DashboardProps> = ({ role }) => {
    const isStudent = role === 'student';

    const schedule = [
        {
            subject: 'Sejarah',
            teacher: 'Pak Yostiar H20',
            time: '07.00-07.45',
            status: 'Hadir',
            color: 'bg-emerald-100 text-emerald-700',
        },
        {
            subject: 'Bahasa Inggris',
            teacher: 'Pak Dipa H18',
            time: '07.45-08.30',
            status: 'Hadir',
            color: 'bg-emerald-100 text-emerald-700',
        },
        {
            subject: 'Fisika',
            teacher: 'Pak Yudika H19',
            time: '07.00-07.45',
            status: 'Aktif',
            color: 'bg-emerald-100 text-emerald-700',
            active: true,
        },
        {
            subject: 'Kimia',
            teacher: 'Bu Revalina Lab Kimia',
            time: '08.45-09.30',
            status: 'Akan Datang',
            color: 'bg-gray-200 text-gray-500',
        },
        {
            subject: 'Bahasa Inggris',
            teacher: 'Bu Adinda H18',
            time: '09.30-10.15',
            status: 'Akan Datang',
            color: 'bg-gray-200 text-gray-500',
        },
    ];

    return (
        <div className="min-h-screen bg-slate-50 font-sans">
            {/* Header */}
            <div className="relative overflow-hidden rounded-br-[80px] bg-[#1E3A8A] px-6 pt-12 pb-16">
                <div className="absolute top-0 right-0 -mt-20 -mr-20 h-64 w-64 rounded-full bg-white/5"></div>
                <p className="text-sm font-medium text-blue-100">
                    Minggu, 13 Sep 2025
                </p>
                <h1 className="mt-1 text-2xl font-bold text-white">
                    Selamat Datang, {isStudent ? 'Yostiar!' : 'Pak Yudika!'}
                </h1>
            </div>

            {/* Active Class Card */}
            <div className="relative z-10 -mt-10 px-5">
                <div className="rounded-2xl border border-blue-800 bg-[#254BA0] p-5 shadow-lg">
                    <div className="mb-6 flex items-start justify-between">
                        <div>
                            <p className="text-xs tracking-wider text-blue-200">
                                SEDANG BERLANGSUNG
                            </p>
                            <h2 className="mt-1 text-2xl font-bold text-white">
                                Fisika
                            </h2>
                            <p className="mt-1 text-sm text-blue-200">
                                Pak Yudika Ruang H19
                            </p>
                        </div>
                        <div className="rounded-lg border border-[#2e5bbd] bg-[#1c3a82] px-3 py-2 text-center">
                            <p className="text-[10px] text-blue-200">
                                Sisa Waktu
                            </p>
                            <p className="text-lg font-bold text-teal-400">
                                34:20
                            </p>
                        </div>
                    </div>

                    <button className="flex w-full items-center justify-center gap-2 rounded-xl bg-white py-3.5 font-semibold text-gray-800 transition-transform active:scale-95">
                        {isStudent ? (
                            <>
                                <Camera size={20} className="text-gray-500" />{' '}
                                Scan QR Absensi
                            </>
                        ) : (
                            'Generate QR Code'
                        )}
                    </button>
                </div>
            </div>

            {/* Timeline Schedule */}
            <div className="mt-6 px-5">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="font-bold text-[#1E3A8A]">
                        Jadwal Hari Ini
                    </h3>
                    <button className="text-sm font-semibold text-blue-600">
                        Lihat Semua
                    </button>
                </div>

                <div className="relative ml-3 space-y-4 border-l-2 border-gray-200 pb-6">
                    {schedule.map((item, idx) => (
                        <div key={idx} className="relative pl-6">
                            {/* Timeline Node */}
                            <span
                                className={`absolute top-4 -left-[7px] h-3 w-3 rounded-full border-2 border-white ${item.active ? 'bg-blue-600' : item.status === 'Hadir' ? 'bg-teal-400' : 'bg-gray-300'}`}
                            ></span>

                            {/* Card */}
                            <div
                                className={`flex items-center justify-between rounded-xl p-4 ${item.active ? 'border border-blue-100 bg-white shadow-sm' : 'bg-transparent'}`}
                            >
                                <div>
                                    <h4
                                        className={`font-bold ${item.active ? 'text-blue-600' : 'text-teal-600'}`}
                                    >
                                        {item.subject}
                                    </h4>
                                    <p className="mt-1 text-xs text-gray-500">
                                        {item.teacher}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="mb-1 text-[10px] text-gray-400">
                                        {item.time}
                                    </p>
                                    <span
                                        className={`rounded-md px-3 py-1 text-[10px] font-bold ${item.color}`}
                                    >
                                        {item.status}
                                    </span>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
};

export default Dashboard;
