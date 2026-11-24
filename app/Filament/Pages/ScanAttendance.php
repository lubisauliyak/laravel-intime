<?php

namespace App\Filament\Pages;

use App\Models\Attendance;
use BackedEnum;
use Filament\Pages\Page;

class ScanAttendance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $title = 'Buka Scanner';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'scanner.live';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('scanAttendance', Attendance::class) ?? false;
    }
}
