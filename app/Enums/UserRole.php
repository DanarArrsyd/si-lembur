<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Peran akun yang bisa login.
 */
enum UserRole: string
{
    use HasValues;

    case ADMIN = 'ADMIN';
    case SUPERVISOR = 'SUPERVISOR';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::SUPERVISOR => 'Supervisor',
        };
    }
}
