<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use BackedEnum;

class ScanAttendance extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $title = 'Buka Scanner';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'scanner.live';
}
