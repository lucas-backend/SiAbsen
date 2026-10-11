import React, { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Camera, QrCode, LogOut, ArrowRight, UserCheck } from 'lucide-react';
import type { Auth } from '@/types';

interface DashboardProps {
    role?: 'student' | 'teacher';
}

export default function Dashboard({ role: initialRole = 'student' }: DashboardProps) {
    const { auth } = usePage<{ auth?: Auth }>().props;
    const [currentRole, setCurrentRole] = useState<'student' | 'teacher'>(
        (auth?.user?.role as 'student' | 'teacher') || initialRole
    );

    const isStudent = currentRole === 'student';

    const schedule = [
        { subject: 'Sejarah', teacher: 'Pak Yostiar H20', time: '07.00-07.45', status: 'Hadir', color: 'bg-emerald-100 text-emerald-700' },
        { subject: 'Bahasa Inggris', teacher: 'Pak Dipa H18', time: '07.45-08.30', status: 'Hadir', color: 'bg-emerald-100 text-emerald-700' },
        { subject: 'Fisika', teacher: 'Pak Yudika H19', time: '07.00-07.45', status: 'Aktif', color: 'bg-emerald-100 text-emerald-700', active: true },
        { subject: 'Kimia', teacher: 'Bu Revalina Lab Kimia', time: '08.45-09.30', status: 'Akan Datang', color: 'bg-gray-200 text-gray-500' },
        { subject: 'Bahasa Inggris', teacher: 'Bu Adinda H18', time: '09.30-10.15', status: 'Akan Datang', color: 'bg-gray-200 text-gray-500' },
    ];

    return (
        <>
            <Head title="Dashboard - EduPresence" />
            <div className="min-h-screen bg-slate-50 font-sans max-w-md mx-auto shadow-2xl relative pb-8">
                {/* Header */}
                <div className="bg-[#1E3A8A] pt-12 pb-16 px-6 relative overflow-hidden rounded-br-[80px]">
                    <div className="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -mr-20 -mt-20"></div>

                    {/* Role switcher & Logout badge */}
                    <div className="flex justify-between items-center mb-4 relative z-10">
                        <button
                            type="button"
                            onClick={() => setCurrentRole(isStudent ? 'teacher' : 'student')}
                            className="bg-white/15 hover:bg-white/25 text-white text-[11px] font-medium px-3 py-1 rounded-full flex items-center gap-1.5 transition-colors"
                        >
                            <UserCheck size={13} />
                            Mode: {isStudent ? 'Siswa' : 'Guru'} (Ganti)
                        </button>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="text-blue-200 hover:text-white p-1"
                            title="Keluar"
                        >
                            <LogOut size={18} />
                        </Link>
                    </div>

                    <p className="text-sm font-medium text-blue-100">Minggu, 13 Sep 2025</p>
                    <h1 className="text-2xl font-bold text-white mt-1">
                        Selamat Datang, {isStudent ? (auth?.user?.name || 'Yostiar!') : 'Pak Yudika!'}
                    </h1>
                </div>

                {/* Active Class Card */}
                <div className="px-5 -mt-10 relative z-10">
                    <div className="bg-[#254BA0] rounded-2xl p-5 shadow-lg border border-blue-800">
                        <div className="flex justify-between items-start mb-6">
                            <div>
                                <p className="text-xs text-blue-200 tracking-wider">SEDANG BERLANGSUNG</p>
                                <h2 className="text-2xl font-bold text-white mt-1">Fisika</h2>
                                <p className="text-sm text-blue-200 mt-1">Pak Yudika Ruang H19</p>
                            </div>
                            <div className="bg-[#1c3a82] border border-[#2e5bbd] rounded-lg px-3 py-2 text-center">
                                <p className="text-[10px] text-blue-200">Sisa Waktu</p>
                                <p className="text-lg font-bold text-teal-400">34:20</p>
                            </div>
                        </div>

                        {isStudent ? (
                            <Link
                                href="/student/scan"
                                className="w-full bg-white text-gray-800 font-semibold py-3.5 rounded-xl flex items-center justify-center gap-2 active:scale-95 transition-transform shadow-md"
                            >
                                <Camera size={20} className="text-gray-500" /> Scan QR Absensi
                            </Link>
                        ) : (
                            <Link
                                href="/teacher/class"
                                className="w-full bg-white text-gray-800 font-semibold py-3.5 rounded-xl flex items-center justify-center gap-2 active:scale-95 transition-transform shadow-md"
                            >
                                <QrCode size={20} className="text-gray-500" /> Generate QR Code
                            </Link>
                        )}
                    </div>
                </div>

                {/* Quick Navigation Menu */}
                <div className="px-5 mt-4 grid grid-cols-2 gap-3">
                    <Link
                        href="/student/scan"
                        className="bg-white p-3 rounded-xl border border-gray-100 shadow-xs flex items-center justify-between text-xs font-semibold text-[#1E3A8A] hover:bg-blue-50/50"
                    >
                        <span>Halaman Scan QR</span>
                        <ArrowRight size={14} />
                    </Link>
                    <Link
                        href="/teacher/class"
                        className="bg-white p-3 rounded-xl border border-gray-100 shadow-xs flex items-center justify-between text-xs font-semibold text-[#1E3A8A] hover:bg-blue-50/50"
                    >
                        <span>Tampilan Guru</span>
                        <ArrowRight size={14} />
                    </Link>
                </div>

                {/* Timeline Schedule */}
                <div className="px-5 mt-6">
                    <div className="flex justify-between items-center mb-4">
                        <h3 className="font-bold text-[#1E3A8A]">Jadwal Hari Ini</h3>
                        <Link href="/student/permission" className="text-xs font-semibold text-blue-600 hover:underline">
                            + Buat Izin
                        </Link>
                    </div>

                    <div className="relative border-l-2 border-gray-200 ml-3 space-y-4 pb-6">
                        {schedule.map((item, idx) => (
                            <div key={idx} className="relative pl-6">
                                {/* Timeline Node */}
                                <span className={`absolute -left-[7px] top-4 w-3 h-3 rounded-full border-2 border-white ${item.active ? 'bg-blue-600' : (item.status === 'Hadir' ? 'bg-teal-400' : 'bg-gray-300')}`}></span>

                                {/* Card */}
                                <div className={`p-4 rounded-xl flex justify-between items-center ${item.active ? 'bg-white shadow-sm border border-blue-100' : 'bg-transparent'}`}>
                                    <div>
                                        <h4 className={`font-bold ${item.active ? 'text-blue-600' : 'text-teal-600'}`}>{item.subject}</h4>
                                        <p className="text-xs text-gray-500 mt-1">{item.teacher}</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-[10px] text-gray-400 mb-1">{item.time}</p>
                                        <span className={`text-[10px] font-bold px-3 py-1 rounded-md ${item.color}`}>
                                            {item.status}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}
