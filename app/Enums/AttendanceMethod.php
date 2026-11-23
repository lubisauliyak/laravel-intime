<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AttendanceMethod: string implements HasLabel, HasColor, HasIcon
{
    case MANUAL = 'manual';
    case QR_CODE = 'qr_code';
    case SELF = 'self';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MANUAL => 'MANUAL',
            self::QR_CODE => 'QR CODE',
            self::SELF => 'MANDIRI',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MANUAL => 'info',
            self::QR_CODE => 'success',
            self::SELF => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::MANUAL => 'heroicon-m-pencil-square',
            self::QR_CODE => 'heroicon-m-qr-code',
            self::SELF => 'heroicon-m-device-phone-mobile',
        };
    }
}
