export const ATTENDANCE_QR_TYPE = 'siabsen.attendance';

export type AttendanceQrPayload = {
    sessionId: number;
    qrPayload: string;
};

/**
 * Payload yang tertanam pada QR kelas. Disimpan sebagai JSON agar scanner
 * dapat mengetahui sesi mana yang harus di-scan tanpa konfigurasi tambahan.
 */
export function encodeAttendanceQr(payload: AttendanceQrPayload): string {
    return JSON.stringify({
        type: ATTENDANCE_QR_TYPE,
        session_id: payload.sessionId,
        qr_payload: payload.qrPayload,
    });
}

/**
 * Terima JSON payload, URL scan, atau token mentah bila sesi diketahui.
 */
export function decodeAttendanceQr(text: string): AttendanceQrPayload | null {
    const trimmed = text.trim();

    try {
        const parsed = JSON.parse(trimmed) as Record<string, unknown>;
        const sessionId = Number(parsed.session_id ?? parsed.sessionId);
        const qrPayload =
            parsed.qr_payload ??
            parsed.qrPayload ??
            parsed.token ??
            parsed.payload;

        if (
            Number.isFinite(sessionId) &&
            typeof qrPayload === 'string' &&
            qrPayload !== ''
        ) {
            return { sessionId, qrPayload };
        }
    } catch {
        // Bukan JSON; coba bentuk URL di bawah.
    }

    const match = trimmed.match(/\/attendance\/sessions\/(\d+)\/scan(?:\?|$)/);

    if (match) {
        const sessionId = Number(match[1]);
        const url = new URL(trimmed, window.location.origin);
        const qrPayload = url.searchParams.get('qr_payload') ?? '';

        if (Number.isFinite(sessionId) && qrPayload !== '') {
            return { sessionId, qrPayload };
        }
    }

    return null;
}
