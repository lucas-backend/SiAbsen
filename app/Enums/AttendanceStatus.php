<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Hadir = 'HADIR';
    case Terlambat = 'TERLAMBAT';
    case TidakHadir = 'TIDAK_HADIR';
    case Izin = 'IZIN';
    case Sakit = 'SAKIT';

    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Terlambat => 'Terlambat',
            self::TidakHadir => 'Tidak Hadir',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
        };
    }
}
