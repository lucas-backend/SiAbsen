import React, { useState, useEffect } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { Eye, EyeOff, X } from 'lucide-react';

interface AuthScreenProps {
    initialState?: 'splash' | 'login';
    status?: string;
}

export default function Login({ initialState = 'splash', status }: AuthScreenProps) {
    const [view, setView] = useState(initialState);
    const [showModal, setShowModal] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: true,
    });

    useEffect(() => {
        if (view === 'splash') {
            const timer = setTimeout(() => setView('login'), 2000);
            return () => clearTimeout(timer);
        }
    }, [view]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/login', {
            onError: () => {
                // Error state handled by form
            },
        });
    };

    if (view === 'splash') {
        return (
            <>
                <Head title="EduPresence" />
                <div
                    onClick={() => setView('login')}
                    className="flex flex-col items-center justify-center min-h-screen bg-[#1E3A8A] cursor-pointer select-none"
                >
                    <h1 className="text-3xl font-medium text-white italic tracking-wide animate-pulse">
                        edupresence
                    </h1>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Masuk Ke Akun - EduPresence" />
            <div className="min-h-screen bg-slate-50 relative flex flex-col justify-between max-w-md mx-auto shadow-2xl">
                <div>
                    {/* Header Biru */}
                    <div className="bg-[#1E3A8A] pt-12 pb-8 px-6 rounded-br-[100px] relative overflow-hidden">
                        <div className="absolute top-0 right-0 w-48 h-48 bg-white/5 rounded-full -mr-10 -mt-10 blur-sm"></div>
                        <h2 className="text-xl text-center font-medium text-white italic mb-8">edupresence</h2>
                        <p className="text-sm text-blue-100">Selamat datang user!</p>
                        <h1 className="text-2xl font-bold text-white mt-1">Masuk Ke Akun Anda</h1>
                    </div>

                    {status && (
                        <div className="mx-6 mt-4 p-3 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-medium">
                            {status}
                        </div>
                    )}

                    {/* Form Login */}
                    <form onSubmit={handleSubmit} className="px-6 mt-10 space-y-5">
                        <div>
                            <label className="block text-xs font-bold text-gray-500 mb-2">EMAIL SEKOLAH</label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="yostiarjagodesain@students.unila.ac.id"
                                required
                                className="w-full px-4 py-4 bg-white rounded-xl text-sm border border-gray-100 focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]"
                            />
                            {errors.email && (
                                <p className="text-rose-500 text-xs mt-1 font-medium">{errors.email}</p>
                            )}
                        </div>

                        <div className="relative">
                            <label className="block text-xs font-bold text-gray-500 mb-2">KATA SANDI</label>
                            <input
                                type={showPassword ? 'text' : 'password'}
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="***************"
                                required
                                className="w-full px-4 py-4 bg-white rounded-xl text-sm border border-gray-100 focus:outline-none focus:ring-2 focus:ring-[#1E3A8A] pr-12"
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword(!showPassword)}
                                className="absolute right-4 top-10 text-gray-400 hover:text-gray-600"
                            >
                                {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
                            </button>
                            {errors.password && (
                                <p className="text-rose-500 text-xs mt-1 font-medium">{errors.password}</p>
                            )}
                        </div>

                        <div className="flex justify-end">
                            <button
                                type="button"
                                onClick={() => setShowModal(true)}
                                className="text-xs text-[#1E3A8A] font-semibold hover:underline"
                            >
                                Lupa kata sandi?
                            </button>
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full bg-[#1E3A8A] text-white font-semibold py-4 rounded-xl mt-4 active:scale-95 transition-transform disabled:opacity-70 shadow-md"
                        >
                            {processing ? 'Memproses...' : 'Login'}
                        </button>

                        {/* Tombol Akses Cepat Dashboard untuk Preview */}
                        <div className="pt-2 text-center">
                            <button
                                type="button"
                                onClick={() => router.visit('/dashboard')}
                                className="text-xs text-gray-400 hover:text-[#1E3A8A] transition-colors"
                            >
                                &rarr; Masuk Langsung ke Dashboard (Demo)
                            </button>
                        </div>
                    </form>
                </div>

                <div className="py-6 text-center text-xs text-gray-400">
                    EduPresence &copy; {new Date().getFullYear()}
                </div>

                {/* Modal Peringatan */}
                {showModal && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-6 z-50 backdrop-blur-xs">
                        <div className="bg-white rounded-2xl p-6 w-full max-w-sm text-center relative shadow-2xl">
                            <p className="text-sm text-gray-500 mb-2">Peringatan</p>
                            <p className="text-base font-medium text-gray-900 leading-relaxed">
                                untuk mengubah password silahkan hubungi TU
                            </p>
                            <button
                                type="button"
                                onClick={() => setShowModal(false)}
                                className="absolute -bottom-16 left-1/2 transform -translate-x-1/2 w-10 h-10 border border-white/50 rounded-lg flex items-center justify-center text-white hover:bg-white/10"
                                aria-label="Tutup"
                            >
                                <X size={20} />
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
