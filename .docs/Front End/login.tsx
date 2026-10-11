import React, { useState, useEffect } from 'react';
import { Eye, X } from 'lucide-react'; // Gunakan lucide-react untuk ikon

interface AuthScreenProps {
    initialState?: 'splash' | 'login';
}

const AuthScreen: React.FC<AuthScreenProps> = ({ initialState = 'splash' }) => {
    const [view, setView] = useState(initialState);
    const [showModal, setShowModal] = useState(false); // Untuk modal gambar 14

    useEffect(() => {
        if (view === 'splash') {
            const timer = setTimeout(() => setView('login'), 2000);
            return () => clearTimeout(timer);
        }
    }, [view]);

    if (view === 'splash') {
        return (
            <div className="flex min-h-screen items-center justify-center bg-[#1E3A8A]">
                <h1 className="text-3xl font-medium tracking-wide text-white italic">
                    edupresence
                </h1>
            </div>
        );
    }

    return (
        <div className="relative min-h-screen bg-slate-50">
            {/* Header Biru */}
            <div className="relative overflow-hidden rounded-br-[100px] bg-[#1E3A8A] px-6 pt-12 pb-8">
                <div className="absolute top-0 right-0 -mt-10 -mr-10 h-48 w-48 rounded-full bg-white/5 blur-sm"></div>
                <h2 className="mb-8 text-center text-xl font-medium text-white italic">
                    edupresence
                </h2>
                <p className="text-sm text-blue-100">Selamat datang user!</p>
                <h1 className="mt-1 text-2xl font-bold text-white">
                    Masuk Ke Akun Anda
                </h1>
            </div>

            {/* Form Login */}
            <div className="mt-10 space-y-5 px-6">
                <div>
                    <label className="mb-2 block text-xs font-bold text-gray-500">
                        EMAIL SEKOLAH
                    </label>
                    <input
                        type="email"
                        placeholder="yostiarjagodesain@students.unila.ac.id"
                        className="w-full rounded-xl border border-gray-100 bg-white px-4 py-4 text-sm focus:ring-2 focus:ring-[#1E3A8A] focus:outline-none"
                    />
                </div>

                <div className="relative">
                    <label className="mb-2 block text-xs font-bold text-gray-500">
                        KATA SANDI
                    </label>
                    <input
                        type="password"
                        placeholder="***************"
                        className="w-full rounded-xl border border-gray-100 bg-white px-4 py-4 text-sm focus:ring-2 focus:ring-[#1E3A8A] focus:outline-none"
                    />
                    <button
                        onClick={() => setShowModal(true)}
                        className="absolute top-10 right-4 text-gray-400"
                    >
                        <Eye size={20} />
                    </button>
                </div>

                <button className="mt-4 w-full rounded-xl bg-[#1E3A8A] py-4 font-semibold text-white transition-transform active:scale-95">
                    Login
                </button>
            </div>

            {/* Modal Peringatan */}
            {showModal && (
                <div className="absolute inset-0 z-50 flex items-center justify-center bg-black/50 p-6">
                    <div className="relative w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
                        <p className="mb-2 text-sm text-gray-500">Peringatan</p>
                        <p className="text-base font-medium text-gray-900">
                            untuk mengubah password silahkan hubungi TU
                        </p>
                        <button
                            onClick={() => setShowModal(false)}
                            className="absolute -bottom-16 left-1/2 flex h-10 w-10 -translate-x-1/2 transform items-center justify-center rounded-lg border border-white/50 text-white"
                        >
                            <X size={20} />
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
};

export default AuthScreen;
