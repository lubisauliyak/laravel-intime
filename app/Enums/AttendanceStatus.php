<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AttendanceStatus: string implements HasLabel, HasColor, HasIcon
{
    case HADIR = 'hadir';
    case IZIN = 'izin';
    case SAKIT = 'sakit';
    case ALFA = 'alfa';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::HADIR => 'HADIR',
            self::IZIN => 'IZIN',
            self::SAKIT => 'SAKIT',
            self::ALFA => 'ALPA',
        };
    }

    public function getCode(): string
    {
        return match ($this) {
            self::HADIR => 'H',
            self::IZIN => 'I',
            self::SAKIT => 'S',
            self::ALFA => 'A',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::HADIR => 'success',
            self::IZIN => 'warning',
            self::SAKIT => 'danger',
            self::ALFA => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::HADIR => 'heroicon-m-check-circle',
            self::IZIN => 'heroicon-m-envelope',
            self::SAKIT => 'heroicon-m-heart',
            self::ALFA => 'heroicon-m-x-circle',
        };
    }
}
