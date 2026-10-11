<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Zona Waktu Sekolah
    |--------------------------------------------------------------------------
    |
    | Zona waktu yang dipakai untuk menafsirkan tanggal sesi (`session_date`)
    | dan waktu input (`start_at`, `end_at`) serta menyajikan batas jendela scan.
    |
    */

    'timezone' => env('ATTENDANCE_TIMEZONE', 'Asia/Jakarta'),

    /*
    |--------------------------------------------------------------------------
    | Toleransi Keterlambatan
    |--------------------------------------------------------------------------
    |
    | Jumlah menit setelah `start_at` yang masih dianggap `HADIR`. Scan setelah
    | batas ini menghasilkan `TERLAMBAT` dengan selisih menit keterlambatan.
    |
    */

    'late_tolerance_minutes' => (int) env('ATTENDANCE_LATE_TOLERANCE_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Radius Geofence Default
    |--------------------------------------------------------------------------
    |
    | Radius (meter) yang dipakai bila kelas/sekolah tidak mendefinisikan
    | `radius_meters`.
    |
    */

    'default_radius_meters' => (int) env('ATTENDANCE_DEFAULT_RADIUS_METERS', 50),

    /*
    |--------------------------------------------------------------------------
    | Batas Jendela Scan
    |--------------------------------------------------------------------------
    |
    | Jendela scan membuka `opens_before_minutes` sebelum `start_at` dan
    | menutup tepat pada `end_at`. Scan di luar jendela ditolak.
    |
    */

    'scan_window' => [
        'opens_before_minutes' => (int) env('ATTENDANCE_SCAN_OPENS_BEFORE_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Durasi Sesi Default
    |--------------------------------------------------------------------------
    |
    | Dipakai bila `end_at` tidak diberikan saat membuka sesi.
    |
    */

    'default_duration_minutes' => (int) env('ATTENDANCE_DEFAULT_DURATION_MINUTES', 120),

];
