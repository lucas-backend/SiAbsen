<?php

namespace App\Enums;

enum LocationStatus: string
{
    case Sesuai = 'SESUAI';
    case DiLuarRadius = 'DI_LUAR_RADIUS';
    case TidakTerverifikasi = 'TIDAK_TERVERIFIKASI';

    public function label(): string
    {
        return match ($this) {
            self::Sesuai => 'Sesuai',
            self::DiLuarRadius => 'Di Luar Radius',
            self::TidakTerverifikasi => 'Tidak Terverifikasi',
        };
    }
}
