import React from 'react';
import { AlertCircle, X } from 'lucide-react';

interface AlertModalProps {
    isOpen: boolean;
    onClose: () => void;
}

const OutOfRangeAlertModal: React.FC<AlertModalProps> = ({ isOpen, onClose }) => {
    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-6 backdrop-blur-sm">
            <div className="bg-white rounded-[24px] p-6 w-full max-w-sm text-center relative shadow-2xl pt-14">

                {/* Ikon Tanda Seru Melayang */}
                <div className="absolute -top-12 left-1/2 transform -translate-x-1/2">
                    <div className="bg-[#1E3A8A] text-white rounded-full p-4 shadow-lg border-4 border-white">
                        <AlertCircle size={60} strokeWidth={2.5} />
                    </div>
                </div>

                <p className="text-sm text-gray-500 mb-2 font-medium">Peringatan</p>
                <p className="text-[15px] font-semibold text-gray-900 leading-snug">
                    Absen diluar jangkauan<br />
                    coba anunya dianukan lagi
                </p>

                {/* Tombol Tutup (X) di Bawah Modal */}
                <button
                    onClick={onClose}
                    className="absolute -bottom-16 left-1/2 transform -translate-x-1/2 w-10 h-10 border-2 border-white/60 rounded-lg flex items-center justify-center text-white hover:bg-white/10 transition-colors"
                >
                    <X size={20} />
                </button>
            </div>
        </div>
    );
};

export default OutOfRangeAlertModal;