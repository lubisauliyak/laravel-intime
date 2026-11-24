<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case OPERATOR = 'operator';
    case PENGURUS = 'pengurus';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'SUPERADMIN',
            self::ADMIN => 'ADMIN',
            self::OPERATOR => 'OPERATOR',
            self::PENGURUS => 'PENGURUS',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SUPER_ADMIN => 'danger',
            self::ADMIN => 'success',
            self::OPERATOR => 'info',
            self::PENGURUS => 'warning',
        };
    }
}
