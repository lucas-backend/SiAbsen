import { useCallback, useState } from 'react';

export type GeolocationStatus =
    | 'idle'
    | 'prompt'
    | 'granted'
    | 'denied'
    | 'unavailable';

export type GeoPosition = {
    latitude: number;
    longitude: number;
    accuracy: number;
};

export type UseGeolocationResult = {
    position: GeoPosition | null;
    error: string | null;
    status: GeolocationStatus;
    isLoading: boolean;
    getPosition: () => Promise<GeoPosition | null>;
};

const PERMISSION_DENIED = 1;

/**
 * Pembungkus `navigator.geolocation`. Bila izin gagal, kembalian posisi
 * `null` sehingga server mengklasifikasikannya sebagai TIDAK_TERVERIFIKASI.
 */
export function useGeolocation(): UseGeolocationResult {
    const [position, setPosition] = useState<GeoPosition | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [status, setStatus] = useState<GeolocationStatus>(() =>
        typeof navigator !== 'undefined' && 'geolocation' in navigator
            ? 'prompt'
            : 'unavailable',
    );
    const [isLoading, setIsLoading] = useState(false);

    const getPosition = useCallback((): Promise<GeoPosition | null> => {
        if (typeof navigator === 'undefined' || !('geolocation' in navigator)) {
            setStatus('unavailable');
            setError('Perangkat tidak mendukung layanan lokasi.');

            return Promise.resolve(null);
        }

        setIsLoading(true);

        return new Promise<GeoPosition | null>((resolve) => {
            navigator.geolocation.getCurrentPosition(
                (result) => {
                    const next: GeoPosition = {
                        latitude: result.coords.latitude,
                        longitude: result.coords.longitude,
                        accuracy: result.coords.accuracy,
                    };

                    setPosition(next);
                    setError(null);
                    setStatus('granted');
                    setIsLoading(false);
                    resolve(next);
                },
                (geoError) => {
                    const denied = geoError.code === PERMISSION_DENIED;

                    setError(
                        denied
                            ? 'Izin lokasi ditolak. Aktifkan GPS agar kehadiran terverifikasi.'
                            : 'Gagal mendapatkan lokasi perangkat. Kehadiran dicatat sebagai tidak terverifikasi.',
                    );
                    setStatus(denied ? 'denied' : 'unavailable');
                    setIsLoading(false);
                    resolve(null);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0,
                },
            );
        });
    }, []);

    return { position, error, status, isLoading, getPosition };
}
