<?php

namespace Tests\Unit\Services;

use App\Models\AttendanceSession;
use App\Services\DynamicQrService;
use PHPUnit\Framework\TestCase;

class DynamicQrServiceTest extends TestCase
{
    public function test_it_generates_valid_dynamic_qr_payload_structure(): void
    {
        $service = new class extends DynamicQrService
        {
            protected function createSignature(int $sessionId, int $expiresAt, string $sessionSecret): string
            {
                return hash_hmac('sha256', "{$sessionId}:{$expiresAt}", $sessionSecret.'test-key');
            }
        };

        $session = new AttendanceSession;
        $session->id = 42;
        $session->qr_payload = bin2hex(random_bytes(32));

        $result = $service->generateToken($session);

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertSame(30, $result['expires_in']);

        $parts = explode('.', $result['token']);
        $this->assertCount(3, $parts);
        $this->assertSame('42', $parts[0]);
        $this->assertSame((string) $result['expires_at'], $parts[1]);
        $this->assertSame(64, strlen($parts[2]));
    }

    public function test_it_rejects_malformed_token(): void
    {
        $service = new DynamicQrService;

        $result = $service->verifyToken('not-a-valid-token');
        $this->assertFalse($result['valid']);
        $this->assertSame('Format token QR dinamis tidak valid.', $result['error']);

        $result2 = $service->verifyToken('abc.def.ghi');
        $this->assertFalse($result2['valid']);
        $this->assertSame('Format token QR dinamis tidak valid.', $result2['error']);
    }

    public function test_it_rejects_expired_token(): void
    {
        $service = new DynamicQrService;

        // Expired beyond grace period (> 35 seconds ago)
        $expiredTime = time() - 40;
        $token = "42.{$expiredTime}.abcdef1234567890";

        $result = $service->verifyToken($token);
        $this->assertFalse($result['valid']);
        $this->assertSame('Token QR sudah kedaluwarsa. Silakan scan QR terbaru.', $result['error']);
    }

    public function test_it_rejects_timestamp_too_far_in_future(): void
    {
        $service = new DynamicQrService;

        $futureTime = time() + 100;
        $token = "42.{$futureTime}.abcdef1234567890";

        $result = $service->verifyToken($token);
        $this->assertFalse($result['valid']);
        $this->assertSame('Token QR tidak valid.', $result['error']);
    }
}
