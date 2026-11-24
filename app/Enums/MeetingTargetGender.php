<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum MeetingTargetGender: string implements HasColor, HasIcon, HasLabel
{
    case ALL = 'all';
    case MALE = 'male';
    case FEMALE = 'female';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ALL => 'SEMUA',
            self::MALE => 'LAKI-LAKI',
            self::FEMALE => 'PEREMPUAN',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ALL => 'gray',
            self::MALE => 'info',
            self::FEMALE => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ALL => 'heroicon-m-users',
            self::MALE => 'heroicon-m-user',
            self::FEMALE => 'heroicon-m-user',
        };
    }
}
