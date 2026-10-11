import React from 'react';
import { AlertCircle, X } from 'lucide-react';

interface AlertModalProps {
    isOpen: boolean;
    onClose: () => void;
}

const OutOfRangeAlertModal: React.FC<AlertModalProps> = ({
    isOpen,
    onClose,
}) => {
    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-6 backdrop-blur-sm">
            <div className="relative w-full max-w-sm rounded-[24px] bg-white p-6 pt-14 text-center shadow-2xl">
                {/* Ikon Tanda Seru Melayang */}
                <div className="absolute -top-12 left-1/2 -translate-x-1/2 transform">
                    <div className="rounded-full border-4 border-white bg-[#1E3A8A] p-4 text-white shadow-lg">
                        <AlertCircle size={60} strokeWidth={2.5} />
                    </div>
                </div>

                <p className="mb-2 text-sm font-medium text-gray-500">
                    Peringatan
                </p>
                <p className="text-[15px] leading-snug font-semibold text-gray-900">
                    Absen diluar jangkauan
                    <br />
                    coba anunya dianukan lagi
                </p>

                {/* Tombol Tutup (X) di Bawah Modal */}
                <button
                    onClick={onClose}
                    className="absolute -bottom-16 left-1/2 flex h-10 w-10 -translate-x-1/2 transform items-center justify-center rounded-lg border-2 border-white/60 text-white transition-colors hover:bg-white/10"
                >
                    <X size={20} />
                </button>
            </div>
        </div>
    );
};

export default OutOfRangeAlertModal;
