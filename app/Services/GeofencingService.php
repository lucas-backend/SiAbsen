<?php

namespace App\Services;

use App\Models\SchoolSetting;
use InvalidArgumentException;

class GeofencingService
{
    /**
     * Hitung jarak GPS siswa dari lokasi sekolah.
     *
     * @return array{
     *     distance_meters: int,
     *     location_status: string
     * }
     */
    public function check(float $latitude, float $longitude): array
    {
        // Validasi koordinat GPS.
        if (
            ! is_finite($latitude) ||
            ! is_finite($longitude) ||
            $latitude < -90 ||
            $latitude > 90 ||
            $longitude < -180 ||
            $longitude > 180
        ) {
            throw new InvalidArgumentException('Koordinat GPS tidak valid.');
        }

        $school = SchoolSetting::query()->first();

        if (! $school) {
            throw new \RuntimeException(
                'Lokasi sekolah belum dikonfigurasi.'
            );
        }

        $distance = $this->calculateDistance(
            $latitude,
            $longitude,
            (float) $school->latitude,
            (float) $school->longitude
        );

        return [
            'distance_meters' => (int) round($distance),
            'location_status' => $distance <= $school->radius_meters
                ? 'IN_RANGE'
                : 'OUT_OF_RANGE',
        ];
    }

    private function calculateDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371000;

        $latDifference = deg2rad($lat2 - $lat1);
        $lonDifference = deg2rad($lon2 - $lon1);

        $a = sin($latDifference / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($lonDifference / 2) ** 2;

        $a = min(1.0, max(0.0, $a));

        $centralAngle = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return $earthRadius * $centralAngle;
    }
}
