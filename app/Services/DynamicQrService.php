<?php

namespace App\Services;

use App\Models\AttendanceSession;

class DynamicQrService
{
    /**
     * Masa berlaku token QR dalam detik.
     */
    public const TOKEN_LIFETIME_SECONDS = 30;

    /**
     * Masa toleransi (grace period) dalam detik untuk mengatasi latensi jaringan saat pergantian token.
     */
    public const GRACE_PERIOD_SECONDS = 5;

    /**
     * Terbitkan payload token QR dinamis yang terikat secara kriptografis ke sesi.
     *
     * @return array{
     *     token: string,
     *     expires_at: int,
     *     expires_in: int
     * }
     */
    public function generateToken(AttendanceSession $session): array
    {
        $now = time();
        $expiresAt = $now + self::TOKEN_LIFETIME_SECONDS;

        $signature = $this->createSignature($session->id, $expiresAt, (string) $session->qr_payload);
        $token = "{$session->id}.{$expiresAt}.{$signature}";

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
            'expires_in' => self::TOKEN_LIFETIME_SECONDS,
        ];
    }

    /**
     * Verifikasi token QR dinamis dan temukan sesi yang bersangkutan.
     *
     * @return array{
     *     valid: bool,
     *     error?: string,
     *     session?: AttendanceSession
     * }
     */
    public function verifyToken(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return [
                'valid' => false,
                'error' => 'Format token QR dinamis tidak valid.',
            ];
        }

        [$sessionIdStr, $expiresAtStr, $signature] = $parts;

        if (! ctype_digit($sessionIdStr) || ! ctype_digit($expiresAtStr)) {
            return [
                'valid' => false,
                'error' => 'Format token QR dinamis tidak valid.',
            ];
        }

        $sessionId = (int) $sessionIdStr;
        $expiresAt = (int) $expiresAtStr;
        $now = time();

        // Validasi apakah token sudah kedaluwarsa (termasuk batas toleransi)
        if ($now > ($expiresAt + self::GRACE_PERIOD_SECONDS)) {
            return [
                'valid' => false,
                'error' => 'Token QR sudah kedaluwarsa. Silakan scan QR terbaru.',
            ];
        }

        // Validasi jika timestamp berada terlalu jauh di masa depan (> 60 detik)
        if ($expiresAt > ($now + 60)) {
            return [
                'valid' => false,
                'error' => 'Token QR tidak valid.',
            ];
        }

        /** @var AttendanceSession|null $session */
        $session = AttendanceSession::find($sessionId);

        if (! $session) {
            return [
                'valid' => false,
                'error' => 'Sesi absensi tidak ditemukan.',
            ];
        }

        $expectedSignature = $this->createSignature($sessionId, $expiresAt, (string) $session->qr_payload);

        if (! hash_equals($expectedSignature, $signature)) {
            return [
                'valid' => false,
                'error' => 'Tanda tangan digital token QR tidak valid.',
            ];
        }

        return [
            'valid' => true,
            'session' => $session,
        ];
    }

    /**
     * Buat HMAC SHA-256 menggunakan rahasia sesi dan kunci aplikasi.
     */
    protected function createSignature(int $sessionId, int $expiresAt, string $sessionSecret): string
    {
        $appKey = (string) config('app.key');
        $payload = "{$sessionId}:{$expiresAt}";

        return hash_hmac('sha256', $payload, $sessionSecret.$appKey);
    }
}
