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
            <div className="flex items-center justify-center min-h-screen bg-[#1E3A8A]">
                <h1 className="text-3xl font-medium text-white italic tracking-wide">edupresence</h1>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-slate-50 relative">
            {/* Header Biru */}
            <div className="bg-[#1E3A8A] pt-12 pb-8 px-6 rounded-br-[100px] relative overflow-hidden">
                <div className="absolute top-0 right-0 w-48 h-48 bg-white/5 rounded-full -mr-10 -mt-10 blur-sm"></div>
                <h2 className="text-xl text-center font-medium text-white italic mb-8">edupresence</h2>
                <p className="text-sm text-blue-100">Selamat datang user!</p>
                <h1 className="text-2xl font-bold text-white mt-1">Masuk Ke Akun Anda</h1>
            </div>

            {/* Form Login */}
            <div className="px-6 mt-10 space-y-5">
                <div>
                    <label className="block text-xs font-bold text-gray-500 mb-2">EMAIL SEKOLAH</label>
                    <input
                        type="email"
                        placeholder="yostiarjagodesain@students.unila.ac.id"
                        className="w-full px-4 py-4 bg-white rounded-xl text-sm border border-gray-100 focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]"
                    />
                </div>

                <div className="relative">
                    <label className="block text-xs font-bold text-gray-500 mb-2">KATA SANDI</label>
                    <input
                        type="password"
                        placeholder="***************"
                        className="w-full px-4 py-4 bg-white rounded-xl text-sm border border-gray-100 focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]"
                    />
                    <button onClick={() => setShowModal(true)} className="absolute right-4 top-10 text-gray-400">
                        <Eye size={20} />
                    </button>
                </div>

                <button className="w-full bg-[#1E3A8A] text-white font-semibold py-4 rounded-xl mt-4 active:scale-95 transition-transform">
                    Login
                </button>
            </div>

            {/* Modal Peringatan */}
            {showModal && (
                <div className="absolute inset-0 bg-black/50 flex items-center justify-center p-6 z-50">
                    <div className="bg-white rounded-2xl p-6 w-full max-w-sm text-center relative shadow-2xl">
                        <p className="text-sm text-gray-500 mb-2">Peringatan</p>
                        <p className="text-base font-medium text-gray-900">
                            untuk mengubah password silahkan hubungi TU
                        </p>
                        <button
                            onClick={() => setShowModal(false)}
                            className="absolute -bottom-16 left-1/2 transform -translate-x-1/2 w-10 h-10 border border-white/50 rounded-lg flex items-center justify-center text-white"
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