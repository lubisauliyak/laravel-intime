<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Gender: string implements HasColor, HasLabel
{
    case MALE = 'male';
    case FEMALE = 'female';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MALE => 'LAKI-LAKI',
            self::FEMALE => 'PEREMPUAN',
        };
    }

    public function getCode(): string
    {
        return match ($this) {
            self::MALE => 'L',
            self::FEMALE => 'P',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MALE => 'info',
            self::FEMALE => 'danger',
        };
    }
}
