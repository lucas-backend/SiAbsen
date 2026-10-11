import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, AlertCircle, Edit2, QrCode } from 'lucide-react';

export default function TeacherClassView() {
    const [activeTab, setActiveTab] = useState<'qr' | 'monitor'>('qr');
    const [filter, setFilter] = useState<'semua' | 'hadir' | 'bolos'>('semua');

    const students = [
        { name: 'Adinda Salsabila', nis: '2215061001', time: '07.48', status: 'Hadir', type: 'hadir', color: 'bg-emerald-100 text-emerald-700' },
        { name: 'Yostiar Aminudin', nis: '2415061016', edit: true, status: 'Belum', type: 'bolos', color: 'bg-rose-100 text-rose-700' },
        { name: 'Surya Bagaskara', nis: '2315061102', edit: true, status: 'Belum', type: 'bolos', color: 'bg-rose-100 text-rose-700' },
        { name: 'I Made Dipa Rama', nis: '2415061001', status: 'Izin', type: 'izin', color: 'bg-gray-200 text-gray-600' },
        { name: 'Zahra Nur Shafa', nis: '2215061003', time: '07.50', warn: true, status: 'Hadir', type: 'hadir', color: 'bg-emerald-100 text-emerald-700' }
    ];

    const filteredStudents = students.filter(s => {
        if (filter === 'semua') return true;
        if (filter === 'hadir') return s.type === 'hadir';
        if (filter === 'bolos') return s.type === 'bolos';
        return true;
    });

    return (
        <>
            <Head title="Tampilan Guru - EduPresence" />
            <div className="min-h-screen bg-slate-50 pb-6 max-w-md mx-auto shadow-2xl relative">
                {/* Header */}
                <div className="bg-[#1E3A8A] pt-12 pb-6 px-4 flex items-center gap-3 relative overflow-hidden">
                    <Link href="/dashboard" className="text-white hover:opacity-80">
                        <ChevronLeft size={28} />
                    </Link>
                    <div>
                        <p className="text-xs text-blue-100">{activeTab === 'qr' ? 'Absensi Kelas' : 'Fisika Pak Yudika H19 07.45-09.30'}</p>
                        <h1 className="text-xl font-bold text-white tracking-wide">
                            {activeTab === 'qr' ? 'QR CODE KELAS' : 'Monitor Kehadiran'}
                        </h1>
                    </div>
                </div>

                {activeTab === 'qr' ? (
                    <div className="px-5 mt-6 space-y-4">
                        {/* Status Bar */}
                        <div className="bg-white rounded-xl p-4 flex justify-between items-center shadow-sm border border-gray-100">
                            <div>
                                <h3 className="font-bold text-blue-600">Fisika</h3>
                                <p className="text-xs text-gray-500 mt-1">Pak Yudika H19 07.45-09.30</p>
                            </div>
                            <span className="bg-emerald-100 text-emerald-700 text-xs font-bold px-3 py-1 rounded-md">Aktif</span>
                        </div>

                        {/* QR Code Container */}
                        <div className="bg-white rounded-2xl p-8 flex flex-col justify-center items-center shadow-sm border border-gray-100 min-h-[300px]">
                            <div className="w-52 h-52 bg-slate-900 rounded-2xl p-4 flex flex-col items-center justify-center text-white relative shadow-inner">
                                <QrCode size={150} className="text-white" />
                                <span className="text-[10px] text-slate-300 mt-2 tracking-widest font-mono">TOKEN: FISIKA-H19-8921</span>
                            </div>
                        </div>

                        <div className="bg-white border border-gray-800 rounded-xl p-4">
                            <p className="text-xs text-center text-gray-800 font-medium">
                                QR berubah setiap 15 menit. Setiap token hanya valid sekali per siswa. Screenshot tidak dapat digunakan.
                            </p>
                        </div>

                        {/* Stats Grid */}
                        <div className="grid grid-cols-3 gap-3 pt-2">
                            {[
                                { label: 'Hadir', count: 22 },
                                { label: 'Belum Hadir', count: 8, icon: true },
                                { label: 'Izin', count: 2 }
                            ].map((stat, i) => (
                                <div key={i} className="bg-white rounded-xl p-4 flex flex-col items-center justify-center shadow-sm border border-gray-100">
                                    {stat.icon && <div className="w-6 h-6 border-2 border-gray-300 rounded-t-md rounded-b-md relative mb-1"></div>}
                                    <p className="text-xl font-bold text-[#1E3A8A]">{stat.count}</p>
                                    <p className="text-[10px] font-bold text-[#1E3A8A] mt-1">{stat.label}</p>
                                </div>
                            ))}
                        </div>

                        {/* Action Buttons */}
                        <div className="flex gap-4 pt-4">
                            <button
                                type="button"
                                onClick={() => setActiveTab('monitor')}
                                className="flex-1 bg-[#1E3A8A] text-white font-semibold py-4 rounded-xl shadow-md active:scale-95 transition-transform"
                            >
                                Monitor Live
                            </button>
                            <button
                                type="button"
                                onClick={() => alert('Token QR berhasil diperbarui!')}
                                className="flex-1 bg-[#1E3A8A] text-white font-semibold py-4 rounded-xl shadow-md active:scale-95 transition-transform"
                            >
                                Ganti QR Code
                            </button>
                        </div>
                    </div>
                ) : (
                    <div>
                        {/* Filter Pills */}
                        <div className="flex gap-2 px-4 py-4 bg-white border-b border-gray-200 overflow-x-auto">
                            <button
                                type="button"
                                onClick={() => setFilter('semua')}
                                className={`${filter === 'semua' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-600'} text-xs font-bold px-4 py-2 rounded-full flex items-center gap-2`}
                            >
                                Semua <span className="bg-blue-500/30 px-1.5 py-0.5 rounded-full text-[10px]">32</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setFilter('hadir')}
                                className={`${filter === 'hadir' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-600'} text-xs font-bold px-4 py-2 rounded-full flex items-center gap-2`}
                            >
                                Hadir <span className="bg-gray-200 text-gray-700 px-1.5 py-0.5 rounded-full text-[10px]">22</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setFilter('bolos')}
                                className={`${filter === 'bolos' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-600'} text-xs font-bold px-4 py-2 rounded-full flex items-center gap-2`}
                            >
                                Bolos <span className="bg-gray-200 text-gray-700 px-1.5 py-0.5 rounded-full text-[10px]">8</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setActiveTab('qr')}
                                className="ml-auto text-xs font-semibold text-blue-600 px-2 py-2"
                            >
                                &larr; QR Code
                            </button>
                        </div>

                        <div className="bg-gray-100 px-4 py-2 flex items-center gap-3 text-gray-500 text-sm border-b border-gray-200">
                            <div className="bg-blue-600 text-white p-1 rounded"><AlertCircle size={16} /></div>
                            3 murid belum absen
                        </div>

                        {/* Student List */}
                        <div className="p-4 space-y-3">
                            {filteredStudents.map((student, idx) => (
                                <div key={idx} className="bg-white rounded-xl p-4 flex justify-between items-center shadow-sm border-l-4 border-l-gray-200">
                                    <div>
                                        <h4 className="font-bold text-gray-900 text-sm">{student.name}</h4>
                                        <p className="text-xs text-gray-500 mt-1">NIS {student.nis}</p>
                                    </div>
                                    <div className="flex flex-col items-end gap-1">
                                        {student.time && <p className="text-[10px] text-gray-500">{student.time}</p>}
                                        <div className="flex items-center gap-2">
                                            {student.warn && <AlertCircle size={14} className="text-amber-500" />}
                                            {student.edit && (
                                                <Link href="/student/permission" title="Edit status izin">
                                                    <Edit2 size={12} className="text-gray-400 hover:text-blue-600" />
                                                </Link>
                                            )}
                                            <span className={`text-[10px] font-bold px-3 py-1 rounded-md ${student.color}`}>{student.status}</span>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
