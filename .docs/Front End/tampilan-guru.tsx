import React, { useState } from 'react';
import { ChevronLeft, AlertCircle, Edit2 } from 'lucide-react';

export default function TeacherClassView() {
    const [activeTab, setActiveTab] = useState<'qr' | 'monitor'>('qr');

    return (
        <div className="min-h-screen bg-slate-50 pb-6">
            {/* Header */}
            <div className="relative flex items-center gap-3 overflow-hidden bg-[#1E3A8A] px-4 pt-12 pb-6">
                <button className="text-white">
                    <ChevronLeft size={28} />
                </button>
                <div>
                    <p className="text-xs text-blue-100">
                        {activeTab === 'qr'
                            ? 'Absensi Kelas'
                            : 'Fisika Pak Yudika H19 07.45-09.30'}
                    </p>
                    <h1 className="text-xl font-bold tracking-wide text-white">
                        {activeTab === 'qr'
                            ? 'QR CODE KELAS'
                            : 'Monitor Kehadiran'}
                    </h1>
                </div>
            </div>

            {activeTab === 'qr' ? (
                <div className="mt-6 space-y-4 px-5">
                    {/* Status Bar */}
                    <div className="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <div>
                            <h3 className="font-bold text-blue-600">Fisika</h3>
                            <p className="mt-1 text-xs text-gray-500">
                                Pak Yudika H19 07.45-09.30
                            </p>
                        </div>
                        <span className="rounded-md bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">
                            Aktif
                        </span>
                    </div>

                    {/* QR Code Container */}
                    <div className="flex min-h-[300px] items-center justify-center rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
                        {/* Placeholder untuk Image QR - Ganti src dengan actual QR */}
                        <img
                            src="/api/placeholder/200/200"
                            alt="QR Code"
                            className="h-48 w-48"
                        />
                    </div>

                    <div className="rounded-xl border border-gray-800 bg-white p-4">
                        <p className="text-center text-xs font-medium text-gray-800">
                            QR berubah setiap 15 menit. Setiap token hanya valid
                            sekali per siswa. Screenshot tidak dapat digunakan.
                        </p>
                    </div>

                    {/* Stats Grid */}
                    <div className="grid grid-cols-3 gap-3 pt-2">
                        {[
                            { label: 'Hadir', count: 22 },
                            { label: 'Belum Hadir', count: 8, icon: true },
                            { label: 'Izin', count: 2 },
                        ].map((stat, i) => (
                            <div
                                key={i}
                                className="flex flex-col items-center justify-center rounded-xl border border-gray-100 bg-white p-4 shadow-sm"
                            >
                                {stat.icon && (
                                    <div className="relative mb-1 h-6 w-6 rounded-t-md rounded-b-md border-2 border-gray-300"></div>
                                )}
                                <p className="text-xl font-bold text-[#1E3A8A]">
                                    {stat.count}
                                </p>
                                <p className="mt-1 text-[10px] font-bold text-[#1E3A8A]">
                                    {stat.label}
                                </p>
                            </div>
                        ))}
                    </div>

                    {/* Action Buttons */}
                    <div className="flex gap-4 pt-4">
                        <button
                            onClick={() => setActiveTab('monitor')}
                            className="flex-1 rounded-xl bg-[#1E3A8A] py-4 font-semibold text-white"
                        >
                            Monitor Live
                        </button>
                        <button className="flex-1 rounded-xl bg-[#1E3A8A] py-4 font-semibold text-white">
                            Ganti QR Code
                        </button>
                    </div>
                </div>
            ) : (
                <div>
                    {/* Filter Pills */}
                    <div className="flex gap-2 overflow-x-auto border-b border-gray-200 bg-white px-4 py-4">
                        <button className="flex items-center gap-2 rounded-full bg-blue-600 px-4 py-2 text-xs font-bold text-white">
                            Semua{' '}
                            <span className="rounded-full bg-blue-500 px-1.5 py-0.5 text-[10px]">
                                32
                            </span>
                        </button>
                        <button className="flex items-center gap-2 rounded-full border border-gray-300 px-4 py-2 text-xs font-bold text-gray-600">
                            Hadir{' '}
                            <span className="rounded-full bg-gray-200 px-1.5 py-0.5 text-[10px]">
                                32
                            </span>
                        </button>
                        <button className="flex items-center gap-2 rounded-full border border-gray-300 px-4 py-2 text-xs font-bold text-gray-600">
                            Bolos{' '}
                            <span className="rounded-full bg-gray-200 px-1.5 py-0.5 text-[10px]">
                                32
                            </span>
                        </button>
                    </div>

                    <div className="flex items-center gap-3 border-b border-gray-200 bg-gray-100 px-4 py-2 text-sm text-gray-500">
                        <div className="rounded bg-blue-600 p-1 text-white">
                            <AlertCircle size={16} />
                        </div>
                        3 murid belum absen
                    </div>

                    {/* Student List */}
                    <div className="space-y-3 p-4">
                        {[
                            {
                                name: 'Adinda Salsabila',
                                nis: '2215061001',
                                time: '07.48',
                                status: 'Hadir',
                                color: 'bg-emerald-100 text-emerald-700',
                            },
                            {
                                name: 'Yostiar Aminudin',
                                nis: '2415061016',
                                edit: true,
                                status: 'Belum',
                                color: 'bg-rose-100 text-rose-700',
                            },
                            {
                                name: 'Surya Bagaskara',
                                nis: '2315061102',
                                edit: true,
                                status: 'Belum',
                                color: 'bg-rose-100 text-rose-700',
                            },
                            {
                                name: 'I Made Dipa Rama',
                                nis: '2415061001',
                                status: 'Izin',
                                color: 'bg-gray-200 text-gray-600',
                            },
                            {
                                name: 'Zahra Nur Shafa',
                                nis: '2215061003',
                                time: '07.50',
                                warn: true,
                                status: 'Hadir',
                                color: 'bg-emerald-100 text-emerald-700',
                            },
                        ].map((student, idx) => (
                            <div
                                key={idx}
                                className="flex items-center justify-between rounded-xl border-l-4 border-l-gray-200 bg-white p-4 shadow-sm"
                            >
                                <div>
                                    <h4 className="text-sm font-bold text-gray-900">
                                        {student.name}
                                    </h4>
                                    <p className="mt-1 text-xs text-gray-500">
                                        NIS {student.nis}
                                    </p>
                                </div>
                                <div className="flex flex-col items-end gap-1">
                                    {student.time && (
                                        <p className="text-[10px] text-gray-500">
                                            {student.time}
                                        </p>
                                    )}
                                    <div className="flex items-center gap-2">
                                        {student.warn && (
                                            <AlertCircle
                                                size={14}
                                                className="text-amber-500"
                                            />
                                        )}
                                        {student.edit && (
                                            <Edit2
                                                size={12}
                                                className="text-gray-400"
                                            />
                                        )}
                                        <span
                                            className={`rounded-md px-3 py-1 text-[10px] font-bold ${student.color}`}
                                        >
                                            {student.status}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
