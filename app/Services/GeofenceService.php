<?php

namespace App\Services;

use App\Enums\LocationStatus;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;

/**
 * Menghitung jarak Haversine dan mengklasifikasikan posisi pemindaian
 * terhadap titik pusat geofence sesi/kelas/sekolah.
 */
class GeofenceService
{
    private const EARTH_RADIUS_METERS = 6371000.0;

    /**
     * Jarak lingkaran besar antara dua koordinat dalam meter.
     */
    public function haversineMeters(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        $a = min(1.0, max(0.0, $a));

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Klasifikasikan posisi siswa terhadap titik pusat dan radius.
     *
     * @return array{status: LocationStatus, distance_meters: int|null}
     */
    public function classify(?float $latitude, ?float $longitude, ?float $centerLatitude, ?float $centerLongitude, int $radiusMeters): array
    {
        if ($latitude === null || $longitude === null || $centerLatitude === null || $centerLongitude === null) {
            return [
                'status' => LocationStatus::TidakTerverifikasi,
                'distance_meters' => null,
            ];
        }

        $distance = $this->haversineMeters($latitude, $longitude, $centerLatitude, $centerLongitude);

        return [
            'status' => $distance <= $radiusMeters ? LocationStatus::Sesuai : LocationStatus::DiLuarRadius,
            'distance_meters' => (int) round($distance),
        ];
    }

    /**
     * Evaluasi geofence untuk sebuah sesi absensi.
     *
     * @return array{status: LocationStatus, distance_meters: int|null}
     */
    public function evaluate(AttendanceSession $session, ?float $latitude, ?float $longitude): array
    {
        $center = $this->resolveCenter($session);

        return $this->classify(
            $latitude,
            $longitude,
            $center['latitude'],
            $center['longitude'],
            $center['radius_meters'],
        );
    }

    /**
     * Tentukan titik pusat dan radius geofence secara berjenjang:
     * sesi → kelas → pengaturan sekolah → radius default config.
     *
     * @return array{latitude: float|null, longitude: float|null, radius_meters: int}
     */
    public function resolveCenter(AttendanceSession $session): array
    {
        $class = $session->schoolClass;

        if ($session->latitude !== null && $session->longitude !== null) {
            return [
                'latitude' => $session->latitude,
                'longitude' => $session->longitude,
                'radius_meters' => $this->classRadius($class),
            ];
        }

        if ($class instanceof SchoolClass && $class->latitude !== null && $class->longitude !== null) {
            return [
                'latitude' => $class->latitude,
                'longitude' => $class->longitude,
                'radius_meters' => $this->classRadius($class),
            ];
        }

        $school = SchoolSetting::query()->first();

        if ($school instanceof SchoolSetting && $school->latitude !== null && $school->longitude !== null) {
            return [
                'latitude' => $school->latitude,
                'longitude' => $school->longitude,
                'radius_meters' => $school->radius_meters ?? $this->defaultRadius(),
            ];
        }

        return [
            'latitude' => null,
            'longitude' => null,
            'radius_meters' => $this->defaultRadius(),
        ];
    }

    private function classRadius(?SchoolClass $class): int
    {
        if ($class instanceof SchoolClass && $class->radius_meters !== null) {
            return $class->radius_meters;
        }

        return $this->defaultRadius();
    }

    private function defaultRadius(): int
    {
        return (int) config('attendance.default_radius_meters', 50);
    }
}
