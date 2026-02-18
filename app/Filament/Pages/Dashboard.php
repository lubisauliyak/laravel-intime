<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    use HasPageShield;

    public function getSubheading(): ?string
    {
        return 'Selamat datang kembali, ' . auth()->user()->name . '!';
    }
}
