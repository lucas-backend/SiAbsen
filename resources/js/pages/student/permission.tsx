import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, Edit2 } from 'lucide-react';

export default function PermissionForm() {
    const [reason, setReason] = useState('');
    const [isSubmitted, setIsSubmitted] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!reason.trim()) {
            alert('Silakan tuliskan alasan izin terlebih dahulu.');
            return;
        }
        setIsSubmitted(true);
        setTimeout(() => {
            alert('Surat permohonan izin berhasil dibuat!');
            router.visit('/dashboard');
        }, 600);
    };

    return (
        <>
            <Head title="Permintaan Izin - EduPresence" />
            <div className="min-h-screen bg-slate-50 flex flex-col font-sans max-w-md mx-auto shadow-2xl relative">
                {/* Header */}
                <div className="bg-[#1E3A8A] pt-12 pb-6 px-4 flex items-center gap-3 relative overflow-hidden shrink-0">
                    <div className="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -mr-20 -mt-20"></div>
                    <Link href="/dashboard" className="text-white relative z-10 hover:opacity-80">
                        <ChevronLeft size={28} />
                    </Link>
                    <div className="relative z-10">
                        <p className="text-xs text-blue-100 font-medium">Permintaan Izin</p>
                        <h1 className="text-xl font-bold text-white tracking-wide">Surya Bagaskara</h1>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="flex-1 px-5 pt-6 pb-8 flex flex-col justify-between">
                    <div>
                        {/* Profil Singkat Siswa */}
                        <div className="bg-white rounded-xl p-4 flex justify-between items-center shadow-sm border border-gray-100 border-l-4 border-l-rose-200 mb-6 shrink-0">
                            <div>
                                <h3 className="font-bold text-gray-900 text-sm">Surya Bagaskara</h3>
                                <p className="text-xs text-gray-500 mt-1">NIS 2315061102</p>
                            </div>
                            <div className="bg-gray-100 p-1.5 rounded-md">
                                <Edit2 size={16} className="text-gray-400" />
                            </div>
                        </div>

                        {/* Textarea Alasan */}
                        <div className="bg-white rounded-xl border border-gray-200 p-4 mb-6 shadow-sm flex flex-col min-h-[220px]">
                            <textarea
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                placeholder="Tulis alasan untuk izin......"
                                rows={8}
                                className="w-full h-full resize-none text-sm text-gray-700 bg-transparent focus:outline-none placeholder:text-gray-400"
                            ></textarea>
                        </div>

                        {/* Info Box */}
                        <div className="border border-gray-800 rounded-xl p-4 mb-6 shrink-0">
                            <p className="text-[11px] font-medium text-center text-black leading-tight">
                                Izin harus disertai surat yang sudah dikirimkan kesekolah dan anunya dianukan dulu biar apalah
                            </p>
                        </div>
                    </div>

                    {/* Tombol Submit */}
                    <button
                        type="submit"
                        disabled={isSubmitted}
                        className="w-full bg-[#254BA0] text-white font-semibold py-4 rounded-xl active:scale-95 transition-transform shrink-0 shadow-md disabled:opacity-60"
                    >
                        {isSubmitted ? 'Mengirim...' : 'Buat Izin'}
                    </button>
                </form>
            </div>
        </>
    );
}
