<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MembershipType: string implements HasColor, HasLabel
{
    case ANGGOTA = 'anggota';
    case PENGURUS = 'pengurus';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ANGGOTA => '-',
            self::PENGURUS => 'PENGURUS',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ANGGOTA => 'gray',
            self::PENGURUS => 'info',
        };
    }
}
