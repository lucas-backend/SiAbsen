import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Eye, EyeOff, X } from 'lucide-react';
import { login } from '@/routes';

interface AuthScreenProps {
    initialState?: 'splash' | 'login';
    status?: string;
}

export default function Login({
    initialState = 'splash',
    status,
}: AuthScreenProps) {
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
        post(login().url, {
            onError: () => {
                // Error state handled by form
            },
        });
    };

    if (view === 'splash') {
        return (
            <>
                <Head title="SiAbsen" />
                <div
                    onClick={() => setView('login')}
                    className="flex min-h-screen cursor-pointer flex-col items-center justify-center bg-[#1E3A8A] select-none"
                >
                    <h1 className="animate-pulse text-3xl font-medium tracking-wide text-white italic">
                        SiAbsen
                    </h1>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Masuk Ke Akun - SiAbsen" />
            <div className="relative mx-auto flex min-h-screen max-w-md flex-col justify-between bg-slate-50 shadow-2xl">
                <div>
                    {/* Header Biru */}
                    <div className="relative overflow-hidden rounded-br-[100px] bg-[#1E3A8A] px-6 pt-12 pb-8">
                        <div className="absolute top-0 right-0 -mt-10 -mr-10 h-48 w-48 rounded-full bg-white/5 blur-sm"></div>
                        <h2 className="mb-8 text-center text-xl font-medium text-white italic">
                            SiAbsen
                        </h2>
                        <p className="text-sm text-blue-100">
                            Selamat datang user!
                        </p>
                        <h1 className="mt-1 text-2xl font-bold text-white">
                            Masuk Ke Akun Anda
                        </h1>
                    </div>

                    {status && (
                        <div className="mx-6 mt-4 rounded-xl bg-emerald-50 p-3 text-xs font-medium text-emerald-700">
                            {status}
                        </div>
                    )}

                    {/* Form Login */}
                    <form
                        onSubmit={handleSubmit}
                        className="mt-10 space-y-5 px-6"
                    >
                        <div>
                            <label className="mb-2 block text-xs font-bold text-gray-500">
                                EMAIL SEKOLAH
                            </label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                placeholder="yostiarjagodesain@students.unila.ac.id"
                                required
                                className="w-full rounded-xl border border-gray-100 bg-white px-4 py-4 text-sm text-black focus:ring-2 focus:ring-[#1E3A8A] focus:outline-none"
                            />
                            {errors.email && (
                                <p className="mt-1 text-xs font-medium text-rose-500">
                                    {errors.email}
                                </p>
                            )}
                        </div>

                        <div className="relative">
                            <label className="mb-2 block text-xs font-bold text-gray-500">
                                KATA SANDI
                            </label>
                            <input
                                type={showPassword ? 'text' : 'password'}
                                value={data.password}
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                                placeholder="***************"
                                required
                                className="w-full rounded-xl border border-gray-100 bg-white px-4 py-4 pr-12 text-sm text-black focus:ring-2 focus:ring-[#1E3A8A] focus:outline-none"
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword(!showPassword)}
                                className="absolute top-10 right-4 text-gray-400 hover:text-gray-600"
                            >
                                {showPassword ? (
                                    <EyeOff size={20} />
                                ) : (
                                    <Eye size={20} />
                                )}
                            </button>
                            {errors.password && (
                                <p className="mt-1 text-xs font-medium text-rose-500">
                                    {errors.password}
                                </p>
                            )}
                        </div>

                        <div className="flex justify-end">
                            <button
                                type="button"
                                onClick={() => setShowModal(true)}
                                className="text-xs font-semibold text-[#1E3A8A] hover:underline"
                            >
                                Lupa kata sandi?
                            </button>
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="mt-4 w-full rounded-xl bg-[#1E3A8A] py-4 font-semibold text-white shadow-md transition-transform active:scale-95 disabled:opacity-70"
                        >
                            {processing ? 'Memproses...' : 'Login'}
                        </button>
                    </form>
                </div>

                <div className="py-6 text-center text-xs text-gray-400">
                    SiAbsen &copy; {new Date().getFullYear()}
                </div>

                {/* Modal Peringatan */}
                {showModal && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-6 backdrop-blur-xs">
                        <div className="relative w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
                            <p className="mb-2 text-sm text-gray-500">
                                Peringatan
                            </p>
                            <p className="text-base leading-relaxed font-medium text-gray-900">
                                untuk mengubah password silahkan hubungi TU
                            </p>
                            <button
                                type="button"
                                onClick={() => setShowModal(false)}
                                className="absolute -bottom-16 left-1/2 flex h-10 w-10 -translate-x-1/2 transform items-center justify-center rounded-lg border border-white/50 text-white hover:bg-white/10"
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
