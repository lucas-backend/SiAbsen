<?php

namespace App\Enums;

enum UserRole: string
{
    case Student = 'STUDENT';
    case Teacher = 'TEACHER';
    case Admin = 'ADMIN';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Siswa',
            self::Teacher => 'Guru',
            self::Admin => 'Admin',
        };
    }
}
