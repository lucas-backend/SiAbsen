import React from 'react';
import { ChevronLeft, Edit2 } from 'lucide-react';

export default function PermissionForm() {
    return (
        <div className="flex min-h-screen flex-col bg-slate-50 font-sans">
            {/* Header */}
            <div className="relative flex shrink-0 items-center gap-3 overflow-hidden bg-[#1E3A8A] px-4 pt-12 pb-6">
                <div className="absolute top-0 right-0 -mt-20 -mr-20 h-64 w-64 rounded-full bg-white/5"></div>
                <button className="relative z-10 text-white">
                    <ChevronLeft size={28} />
                </button>
                <div className="relative z-10">
                    <p className="text-xs font-medium text-blue-100">
                        Permintaan Izin
                    </p>
                    <h1 className="text-xl font-bold tracking-wide text-white">
                        Surya Bagaskara
                    </h1>
                </div>
            </div>

            <div className="flex flex-1 flex-col px-5 pt-6 pb-8">
                {/* Profil Singkat Siswa */}
                <div className="mb-6 flex shrink-0 items-center justify-between rounded-xl border border-l-4 border-gray-100 border-l-rose-200 bg-white p-4 shadow-sm">
                    <div>
                        <h3 className="text-sm font-bold text-gray-900">
                            Surya Bagaskara
                        </h3>
                        <p className="mt-1 text-xs text-gray-500">
                            NIS 2315061102
                        </p>
                    </div>
                    <div className="rounded-md bg-gray-100 p-1.5">
                        <Edit2 size={16} className="text-gray-400" />
                    </div>
                </div>

                {/* Textarea Alasan */}
                <div className="mb-6 flex flex-1 flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <textarea
                        placeholder="Tulis alasan untuk izin......"
                        className="h-full w-full resize-none bg-transparent text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none"
                    ></textarea>
                </div>

                {/* Info Box */}
                <div className="mb-6 shrink-0 rounded-xl border border-gray-800 p-4">
                    <p className="text-center text-[11px] leading-tight font-medium text-black">
                        Izin harus disertai surat yang sudah dikirimkan
                        kesekolah dan anunya dianukan dulu biar apalah
                    </p>
                </div>

                {/* Tombol Submit */}
                <button className="w-full shrink-0 rounded-xl bg-[#254BA0] py-4 font-semibold text-white shadow-md transition-transform active:scale-95">
                    Buat Izin
                </button>
            </div>
        </div>
    );
}
