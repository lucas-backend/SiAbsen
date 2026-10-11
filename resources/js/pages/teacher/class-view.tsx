import React, { useState, useEffect, useCallback, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, AlertCircle, Edit2, QrCode, RefreshCw, Check, Loader2 } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';

interface AttendanceSessionProp {
    id: number;
    assignment_id: number;
    class_id: number;
    session_date?: string;
    start_at: string;
    end_at: string;
    is_active?: boolean;
    assignment?: {
        subject?: { name: string };
        school_class?: { name: string };
    };
    school_class?: { name: string };
}

interface StudentAttendanceItem {
    id: number;
    name: string;
    nis: string;
    status: string; // 'HADIR' | 'BELUM_ABSEN' | 'IZIN' | 'SAKIT'
    time?: string | null;
    warn: boolean; // true if OUT_OF_RANGE
    location_status?: string | null;
    distance_meters?: number | null;
    record_id?: number | null;
    can_edit: boolean;
}

interface AttendanceSummary {
    total: number;
    hadir: number;
    belum_hadir: number;
    izin: number;
    sakit: number;
    izin_sakit: number;
}

interface TeacherClassViewProps {
    initialSession?: AttendanceSessionProp | null;
}

function getXsrfToken(): string | null {
    if (typeof document === 'undefined') return null;
    const cookie = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.split('=')[1]) : null;
}

function badgeColor(status: string, warn?: boolean): string {
    if (status === 'HADIR') {
        return warn ? 'bg-amber-100 text-amber-700 border border-amber-200' : 'bg-emerald-100 text-emerald-700';
    }
    if (status === 'BELUM_ABSEN') return 'bg-rose-100 text-rose-700';
    if (status === 'IZIN') return 'bg-blue-100 text-blue-700';
    if (status === 'SAKIT') return 'bg-purple-100 text-purple-700';
    return 'bg-gray-100 text-gray-600';
}

function statusLabel(status: string, warn?: boolean): string {
    if (status === 'HADIR') return warn ? 'Hadir (Luar)' : 'Hadir';
    if (status === 'BELUM_ABSEN') return 'Belum';
    if (status === 'IZIN') return 'Izin';
    if (status === 'SAKIT') return 'Sakit';
    return status;
}

export default function TeacherClassView({ initialSession = null }: TeacherClassViewProps) {
    const [activeTab, setActiveTab] = useState<'qr' | 'monitor'>('qr');
    const [filter, setFilter] = useState<'semua' | 'hadir' | 'bolos' | 'izin'>('semua');

    // Dynamic QR state
    const [session, setSession] = useState<AttendanceSessionProp | null>(initialSession);
    const [qrToken, setQrToken] = useState<string | null>(null);
    const [countdown, setCountdown] = useState<number>(30);
    const [isLoading, setIsLoading] = useState<boolean>(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [isSessionEnded, setIsSessionEnded] = useState<boolean>(false);
    const isFetchingRef = useRef<boolean>(false);

    // Monitoring state
    const [students, setStudents] = useState<StudentAttendanceItem[]>([]);
    const [summary, setSummary] = useState<AttendanceSummary>({
        total: 0,
        hadir: 0,
        belum_hadir: 0,
        izin: 0,
        sakit: 0,
        izin_sakit: 0,
    });
    const [isMonitoringLoading, setIsMonitoringLoading] = useState<boolean>(false);
    const [monitorError, setMonitorError] = useState<string | null>(null);
    const isFetchingMonitorRef = useRef<boolean>(false);

    // Manual Edit Modal state
    const [selectedStudentForEdit, setSelectedStudentForEdit] = useState<StudentAttendanceItem | null>(null);
    const [showEditModal, setShowEditModal] = useState<boolean>(false);
    const [isSubmittingManual, setIsSubmittingManual] = useState<boolean>(false);

    const filteredStudents = students.filter((s) => {
        if (filter === 'semua') return true;
        if (filter === 'hadir') return s.status === 'HADIR';
        if (filter === 'bolos') return s.status === 'BELUM_ABSEN';
        if (filter === 'izin') return s.status === 'IZIN' || s.status === 'SAKIT';
        return true;
    });

    // Ambil token QR dinamis dari backend
    const fetchQrToken = useCallback(async (targetSessionId?: number) => {
        const sid = targetSessionId ?? session?.id;
        if (!sid || isSessionEnded || isFetchingRef.current) return;

        isFetchingRef.current = true;
        setIsLoading(true);

        try {
            const response = await fetch(`/teacher/attendance-sessions/${sid}/qr-token`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();

            if (response.ok && data?.data?.qr_token) {
                setQrToken(data.data.qr_token);
                setCountdown(data.data.expires_in ?? 30);
                setErrorMessage(null);
                if (data.data.is_active === false) {
                    setIsSessionEnded(true);
                }
            } else if (response.status === 403) {
                setErrorMessage('Anda bukan pemilik penugasan untuk sesi absensi ini.');
            } else if (response.status === 422) {
                setErrorMessage(data?.message || 'Sesi absensi sudah berakhir atau telah ditutup.');
                if (data?.is_active === false) {
                    setIsSessionEnded(true);
                }
            } else {
                setErrorMessage(data?.message || 'Gagal memuat token QR.');
            }
        } catch {
            setErrorMessage('Gagal menghubungi server. Periksa koneksi jaringan Anda.');
        } finally {
            setIsLoading(false);
            isFetchingRef.current = false;
        }
    }, [session?.id, isSessionEnded]);

    // Ambil data monitoring kehadiran siswa dari backend
    const fetchMonitoring = useCallback(async (targetSessionId?: number) => {
        const sid = targetSessionId ?? session?.id;
        if (!sid || isFetchingMonitorRef.current) return;

        isFetchingMonitorRef.current = true;

        try {
            const response = await fetch(`/teacher/attendance-sessions/${sid}/monitor`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();

            if (response.ok && data?.data) {
                setStudents(data.data.students || []);
                setSummary(data.data.summary || {
                    total: 0,
                    hadir: 0,
                    belum_hadir: 0,
                    izin: 0,
                    sakit: 0,
                    izin_sakit: 0,
                });
                setMonitorError(null);
            } else if (response.status === 403) {
                setMonitorError('Anda bukan pemilik penugasan untuk sesi absensi ini.');
            } else {
                setMonitorError(data?.message || 'Gagal memuat data monitoring kehadiran.');
            }
        } catch {
            setMonitorError('Gagal menghubungkan ke server untuk memuat monitoring kehadiran.');
        } finally {
            isFetchingMonitorRef.current = false;
            setIsMonitoringLoading(false);
        }
    }, [session?.id]);

    // Tangani aksi guru mengubah kehadiran manual (SAKIT, IZIN, atau BELUM_ABSEN)
    const handleSaveManualAttendance = useCallback(async (studentId: number, newStatus: 'IZIN' | 'SAKIT' | 'BELUM_ABSEN') => {
        if (!session || isSubmittingManual) return;
        setIsSubmittingManual(true);

        try {
            const xsrfToken = getXsrfToken();
            const response = await fetch(`/teacher/attendance-sessions/${session.id}/manual-attendance`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
                },
                body: JSON.stringify({
                    student_id: studentId,
                    status: newStatus,
                }),
            });

            const data = await response.json();

            if (response.ok) {
                setShowEditModal(false);
                setSelectedStudentForEdit(null);
                // Refresh monitoring seketika
                await fetchMonitoring(session.id);
            } else {
                alert(data?.message || 'Gagal memperbarui status kehadiran siswa.');
            }
        } catch {
            alert('Gagal menghubungi server. Periksa koneksi internet Anda.');
        } finally {
            setIsSubmittingManual(false);
        }
    }, [session, isSubmittingManual, fetchMonitoring]);

    // Jika tidak ada initialSession, cari sesi absensi aktif guru via API
    useEffect(() => {
        if (!session) {
            fetch('/teacher/attendance-sessions', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((res) => res.json())
                .then((data) => {
                    const list = data?.data?.data;
                    if (Array.isArray(list) && list.length > 0) {
                        const active = list.find((s: AttendanceSessionProp) => {
                            const now = new Date();
                            return new Date(s.start_at) <= now && new Date(s.end_at) >= now;
                        }) || list[0];
                        setSession(active);
                    }
                })
                .catch(() => {
                    // Abaikan jika gagal memuat daftar sesi
                });
        }
    }, [session]);

    // Muat QR pertama kali saat sesi tersedia
    useEffect(() => {
        if (session && !isSessionEnded && !qrToken) {
            fetchQrToken(session.id);
        }
    }, [session, isSessionEnded, qrToken, fetchQrToken]);

    // Polling monitoring kehadiran saat tab monitor aktif atau inisialisasi
    useEffect(() => {
        if (!session) return;

        // Ambil data pertama kali
        fetchMonitoring(session.id);

        // Pasang interval polling jika di tab monitor
        if (activeTab === 'monitor') {
            const monitorInterval = setInterval(() => {
                fetchMonitoring(session.id);
            }, 5000);

            return () => clearInterval(monitorInterval);
        }
    }, [session, activeTab, fetchMonitoring]);

    // Timer countdown 1 detik dan auto-refresh QR token (dihentikan saat sesi berakhir atau unmount)
    useEffect(() => {
        if (!session || isSessionEnded || activeTab !== 'qr') return;

        const interval = setInterval(() => {
            setCountdown((prev) => {
                if (prev <= 1) {
                    fetchQrToken(session.id);
                    return 30;
                }
                return prev - 1;
            });
        }, 1000);

        return () => clearInterval(interval);
    }, [session, isSessionEnded, activeTab, fetchQrToken]);

    const subjectName = session?.assignment?.subject?.name || 'Fisika';
    const classTitle = session?.school_class?.name || session?.assignment?.school_class?.name || 'Pak Yudika H19';
    const timeFormatted = session?.start_at && session?.end_at
        ? `${new Date(session.start_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}-${new Date(session.end_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`
        : '07.45-09.30';

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
                        <p className="text-xs text-blue-100">{activeTab === 'qr' ? 'Absensi Kelas' : `${subjectName} ${classTitle} ${timeFormatted}`}</p>
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
                                <h3 className="font-bold text-blue-600">{subjectName}</h3>
                                <p className="text-xs text-gray-500 mt-1">{classTitle} {timeFormatted}</p>
                            </div>
                            <span className={`text-xs font-bold px-3 py-1 rounded-md ${!isSessionEnded ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'}`}>
                                {!isSessionEnded ? 'Aktif' : 'Berakhir'}
                            </span>
                        </div>

                        {/* QR Code Container */}
                        <div className="bg-white rounded-2xl p-8 flex flex-col justify-center items-center shadow-sm border border-gray-100 min-h-[300px]">
                            {session ? (
                                isSessionEnded ? (
                                    <div className="flex flex-col items-center text-center p-4">
                                        <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-3">
                                            <AlertCircle size={32} className="text-gray-400" />
                                        </div>
                                        <p className="font-bold text-gray-800 text-base">Sesi Absensi Berakhir</p>
                                        <p className="text-xs text-gray-500 mt-1 max-w-[220px]">
                                            Sesi absensi ini telah ditutup atau melewati batas waktu. QR Code tidak lagi dapat dipindai.
                                        </p>
                                    </div>
                                ) : errorMessage ? (
                                    <div className="flex flex-col items-center text-center p-4">
                                        <div className="w-16 h-16 bg-rose-50 rounded-full flex items-center justify-center mb-3">
                                            <AlertCircle size={32} className="text-rose-500" />
                                        </div>
                                        <p className="text-xs font-semibold text-rose-600 mb-3 px-2">{errorMessage}</p>
                                        <button
                                            type="button"
                                            onClick={() => fetchQrToken(session.id)}
                                            className="text-xs bg-[#1E3A8A] text-white px-4 py-2 rounded-xl hover:bg-blue-800 transition active:scale-95 shadow-xs"
                                        >
                                            Coba Lagi
                                        </button>
                                    </div>
                                ) : qrToken ? (
                                    <div className="flex flex-col items-center justify-center">
                                        <div className="bg-white p-3.5 rounded-2xl shadow-sm border border-gray-200">
                                            <QRCodeSVG
                                                value={qrToken}
                                                size={185}
                                                level="M"
                                                includeMargin={true}
                                            />
                                        </div>
                                        <div className="flex items-center gap-2 mt-4">
                                            <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            <span className="text-xs font-medium text-gray-600">
                                                QR berganti dalam <span className="font-bold text-blue-700 font-mono text-sm">{countdown}d</span>
                                            </span>
                                        </div>
                                        <span className="text-[10px] text-gray-400 mt-1.5 tracking-wider font-mono">
                                            TOKEN: {qrToken.slice(0, 16)}...
                                        </span>
                                    </div>
                                ) : (
                                    <div className="flex flex-col items-center text-center text-gray-500">
                                        <QrCode size={56} className="text-gray-300 animate-pulse mb-3" />
                                        <p className="text-xs font-medium">Memuat QR Code dinamis...</p>
                                    </div>
                                )
                            ) : (
                                <div className="flex flex-col items-center text-center p-4 text-gray-500">
                                    <QrCode size={56} className="text-gray-300 mb-2" />
                                    <p className="font-bold text-gray-700">Belum Ada Sesi Aktif</p>
                                    <p className="text-xs text-gray-400 mt-1 max-w-[220px]">
                                        Silakan buat sesi absensi terlebih dahulu melalui jadwal mengajar Anda.
                                    </p>
                                </div>
                            )}
                        </div>

                        <div className="bg-white border border-gray-800 rounded-xl p-4">
                            <p className="text-xs text-center text-gray-800 font-medium">
                                QR berubah secara dinamis setiap 30 detik. Setiap token terenkripsi dan terikat pada sesi ini. Screenshot tidak dapat digunakan ulang.
                            </p>
                        </div>

                        {/* Stats Grid */}
                        <div className="grid grid-cols-3 gap-3 pt-2">
                            {[
                                { label: 'Hadir', count: summary.hadir },
                                { label: 'Belum Hadir', count: summary.belum_hadir, icon: true },
                                { label: 'Izin / Sakit', count: summary.izin_sakit }
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
                                onClick={() => {
                                    setActiveTab('monitor');
                                    if (session) fetchMonitoring(session.id);
                                }}
                                className="flex-1 bg-[#1E3A8A] text-white font-semibold py-4 rounded-xl shadow-md active:scale-95 transition-transform"
                            >
                                Monitor Live
                            </button>
                            <button
                                type="button"
                                onClick={() => fetchQrToken()}
                                disabled={isLoading || isSessionEnded || !session}
                                className="flex-1 bg-[#1E3A8A] text-white font-semibold py-4 rounded-xl shadow-md active:scale-95 transition-transform disabled:opacity-50 flex items-center justify-center gap-2"
                            >
                                <RefreshCw size={16} className={isLoading ? 'animate-spin' : ''} />
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
                                className={`${filter === 'semua' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-600'} text-xs font-bold px-4 py-2 rounded-full flex items-center gap-2 transition`}
                            >
                                Semua <span className="bg-blue-500/30 px-1.5 py-0.5 rounded-full text-[10px]">{summary.total}</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setFilter('hadir')}
                                className={`${filter === 'hadir' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-600'} text-xs font-bold px-4 py-2 rounded-full flex items-center gap-2 transition`}
                            >
                                Hadir <span className="bg-gray-200 text-gray-700 px-1.5 py-0.5 rounded-full text-[10px]">{summary.hadir}</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setFilter('bolos')}
                                className={`${filter === 'bolos' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-600'} text-xs font-bold px-4 py-2 rounded-full flex items-center gap-2 transition`}
                            >
                                Belum Hadir <span className="bg-gray-200 text-gray-700 px-1.5 py-0.5 rounded-full text-[10px]">{summary.belum_hadir}</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setFilter('izin')}
                                className={`${filter === 'izin' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-600'} text-xs font-bold px-4 py-2 rounded-full flex items-center gap-2 transition`}
                            >
                                Izin / Sakit <span className="bg-gray-200 text-gray-700 px-1.5 py-0.5 rounded-full text-[10px]">{summary.izin_sakit}</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setActiveTab('qr')}
                                className="ml-auto text-xs font-semibold text-blue-600 px-2 py-2 shrink-0"
                            >
                                &larr; QR Code
                            </button>
                        </div>

                        {/* Banner Ringkasan Belum Hadir */}
                        <div className="bg-gray-100 px-4 py-2 flex items-center justify-between text-gray-600 text-sm border-b border-gray-200">
                            <div className="flex items-center gap-2">
                                <div className="bg-blue-600 text-white p-1 rounded">
                                    <AlertCircle size={16} />
                                </div>
                                <span className="font-medium text-xs">
                                    {summary.belum_hadir} murid belum absen
                                </span>
                            </div>
                            <button
                                type="button"
                                onClick={() => session && fetchMonitoring(session.id)}
                                title="Segarkan data monitoring"
                                className="text-gray-400 hover:text-blue-600 transition"
                            >
                                <RefreshCw size={14} className={isMonitoringLoading ? 'animate-spin' : ''} />
                            </button>
                        </div>

                        {/* Pesan Error jika monitoring gagal */}
                        {monitorError && (
                            <div className="m-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl p-3 text-xs flex items-center gap-2">
                                <AlertCircle size={16} className="text-rose-500 shrink-0" />
                                <span>{monitorError}</span>
                            </div>
                        )}

                        {/* Student List */}
                        <div className="p-4 space-y-3">
                            {filteredStudents.length > 0 ? (
                                filteredStudents.map((student) => (
                                    <div
                                        key={student.id}
                                        className="bg-white rounded-xl p-4 flex justify-between items-center shadow-sm border-l-4 border-l-gray-200"
                                    >
                                        <div>
                                            <h4 className="font-bold text-gray-900 text-sm">{student.name}</h4>
                                            <p className="text-xs text-gray-500 mt-0.5">NIS {student.nis}</p>
                                        </div>
                                        <div className="flex flex-col items-end gap-1">
                                            {student.time && <p className="text-[10px] text-gray-500 font-mono">{student.time}</p>}
                                            <div className="flex items-center gap-2">
                                                {student.warn && (
                                                    <div
                                                        title={`Di luar radius geofence sekolah (${student.distance_meters ?? 0}m)`}
                                                        className="cursor-help flex items-center"
                                                    >
                                                        <AlertCircle size={14} className="text-amber-500" />
                                                    </div>
                                                )}
                                                {student.can_edit && (
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setSelectedStudentForEdit(student);
                                                            setShowEditModal(true);
                                                        }}
                                                        title="Tandai status Izin / Sakit"
                                                        className="p-1 hover:bg-gray-100 rounded-md transition text-gray-400 hover:text-blue-600"
                                                    >
                                                        <Edit2 size={13} />
                                                    </button>
                                                )}
                                                <span className={`text-[10px] font-bold px-2.5 py-1 rounded-md ${badgeColor(student.status, student.warn)}`}>
                                                    {statusLabel(student.status, student.warn)}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="text-center py-12 text-gray-400">
                                    <p className="text-xs font-medium">Tidak ada data siswa untuk filter ini.</p>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* Modal Ubah Status Kehadiran Manual oleh Guru */}
            {showEditModal && selectedStudentForEdit && (
                <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-6 backdrop-blur-xs animate-fade-in">
                    <div className="bg-white rounded-2xl p-6 w-full max-w-sm relative shadow-2xl">
                        <h3 className="text-base font-bold text-gray-900 mb-1">Catat Kehadiran Siswa</h3>
                        <p className="text-xs text-gray-500 mb-4">
                            {selectedStudentForEdit.name} (NIS {selectedStudentForEdit.nis})
                        </p>

                        <div className="space-y-2 mb-6">
                            <button
                                type="button"
                                onClick={() => handleSaveManualAttendance(selectedStudentForEdit.id, 'IZIN')}
                                disabled={isSubmittingManual}
                                className="w-full text-left px-4 py-3 rounded-xl border border-blue-200 bg-blue-50/50 hover:bg-blue-100/60 font-semibold text-xs text-blue-800 flex justify-between items-center transition disabled:opacity-50"
                            >
                                <span>Tandai Sebagai <strong>IZIN</strong></span>
                                {selectedStudentForEdit.status === 'IZIN' && <Check size={16} className="text-blue-600" />}
                            </button>
                            <button
                                type="button"
                                onClick={() => handleSaveManualAttendance(selectedStudentForEdit.id, 'SAKIT')}
                                disabled={isSubmittingManual}
                                className="w-full text-left px-4 py-3 rounded-xl border border-purple-200 bg-purple-50/50 hover:bg-purple-100/60 font-semibold text-xs text-purple-800 flex justify-between items-center transition disabled:opacity-50"
                            >
                                <span>Tandai Sebagai <strong>SAKIT</strong></span>
                                {selectedStudentForEdit.status === 'SAKIT' && <Check size={16} className="text-purple-600" />}
                            </button>
                            {selectedStudentForEdit.status !== 'BELUM_ABSEN' && (
                                <button
                                    type="button"
                                    onClick={() => handleSaveManualAttendance(selectedStudentForEdit.id, 'BELUM_ABSEN')}
                                    disabled={isSubmittingManual}
                                    className="w-full text-left px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 hover:bg-gray-100 font-semibold text-xs text-gray-700 flex justify-between items-center transition disabled:opacity-50"
                                >
                                    <span>Reset ke <strong>BELUM HADIR</strong></span>
                                </button>
                            )}
                        </div>

                        <div className="flex justify-end">
                            <button
                                type="button"
                                onClick={() => {
                                    setShowEditModal(false);
                                    setSelectedStudentForEdit(null);
                                }}
                                disabled={isSubmittingManual}
                                className="px-4 py-2 border border-gray-300 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-50 transition"
                            >
                                {isSubmittingManual ? <Loader2 size={16} className="animate-spin text-gray-500" /> : 'Batal'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
