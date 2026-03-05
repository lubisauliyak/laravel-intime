<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
            'primary' => [
                    50 => '#f0fdf4',
                    100 => '#dcfce7',
                    200 => '#bbf7d0',
                    300 => '#86efac',
                    400 => '#4ade80',
                    500 => '#22c55e',
                    600 => '#16a34a',
                    700 => '#15803d',
                    800 => '#166534',
                    900 => '#14532d',
                    950 => '#0d1511', // inTime Dark BG
                ],
                'gray' => Color::Slate,
            ])
            ->font('Manrope')
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationGroups([
                'Presensi & Laporan',
                'Data Master',
                'Akses Pengguna',
            ])
            ->renderHook(
                'panels::styles.after',
                fn (): string => \Illuminate\Support\Facades\Blade::render("
                    <style>
                        /* ===== inTime THEME (Precise Landing Page Match) ===== */

                        /* 1. LIGHT MODE - Clean, Emerald Accents */
                        body {
                            font-family: 'Manrope', sans-serif !important;
                            background-color: #f6f7f8 !important; /* background-light */
                        }

                        .fi-sidebar {
                            background-color: #ffffff !important;
                            border-right: 1px solid #e2e8f0 !important;
                            box-shadow: 10px 0 30px rgba(0, 0, 0, 0.01) !important;
                        }

                        .fi-sidebar-header {
                            background-color: #ffffff !important;
                            border-bottom: 1px solid #f1f5f9 !important;
                            height: 5rem !important; /* Match landing nav height */
                        }

                        /* Sidebar Group Labels */
                        .fi-sidebar-group-label {
                            color: #64748b !important;
                            font-size: 0.7rem !important;
                            font-weight: 800 !important;
                            letter-spacing: 0.1em;
                            text-transform: uppercase;
                            padding: 1.5rem 1rem 0.5rem !important;
                        }

                        /* Sidebar Items */
                        .fi-sidebar-item-button {
                            border-radius: 0.75rem !important; /* xl from landing */
                            margin: 0.2rem 0.75rem !important;
                            padding: 0.6rem 0.75rem !important;
                            transition: all 0.3s ease !important;
                        }

                        .fi-sidebar-item-button:hover {
                            background-color: #f0fdf4 !important; /* Emerald 50 */
                            transform: translateX(4px);
                        }

                        /* Sidebar Active Item Landing Page Style */
                        .fi-sidebar-item-active .fi-sidebar-item-button {
                            background-color: #065f46 !important; /* primary */
                            box-shadow: 0 10px 15px -3px rgba(6, 95, 70, 0.4) !important;
                        }

                        .fi-sidebar-item-active .fi-sidebar-item-label {
                            color: #ffffff !important;
                            font-weight: 700 !important;
                        }

                        .fi-sidebar-item-active .fi-sidebar-item-icon {
                            color: #ffffff !important;
                        }

                        /* Topbar */
                        .fi-topbar nav {
                            background-color: rgba(255, 255, 255, 0.8) !important;
                            backdrop-filter: blur(12px) !important;
                            border-bottom: 1px solid #e2e8f0 !important;
                            height: 5rem !important;
                        }

                        .fi-main {
                            background-color: #f6f7f8 !important;
                        }

                        /* Table Styles */
                        .fi-ta-header-cell {
                            background-color: #ffffff !important;
                            border-bottom: 1px solid #e2e8f0 !important;
                        }

                        .fi-ta-header-cell-label {
                            color: #0d1511 !important;
                            font-weight: 700 !important;
                        }

                        /* ===== DARK MODE - Emerald Night ===== */

                        .dark body {
                            background-color: #0d1511 !important; /* background-dark dari landing */
                        }

                        .dark .fi-sidebar {
                            background-color: #0d1511 !important;
                            border-right: 1px solid #1e293b !important;
                        }

                        .dark .fi-sidebar-header {
                            background-color: #0d1511 !important;
                            border-bottom: 1px solid #1e293b !important;
                        }

                        .dark .fi-sidebar-group-label {
                            color: #8da399 !important; /* teal-accent dari landing */
                        }

                        .dark .fi-sidebar-item-button:hover {
                            background-color: rgba(6, 95, 70, 0.2) !important;
                        }

                        /* Active Item Dark */
                        .dark .fi-sidebar-item-active .fi-sidebar-item-button {
                            background-color: #065f46 !important;
                            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5) !important;
                        }

                        .dark .fi-sidebar-item-active .fi-sidebar-item-label {
                            color: #ffffff !important;
                        }

                        .dark .fi-topbar nav {
                            background-color: rgba(13, 21, 17, 0.8) !important;
                            border-bottom: 1px solid #1e293b !important;
                        }

                        .dark .fi-main {
                            background-color: #0d1511 !important;
                        }

                        .dark .fi-ta-header-cell {
                            background-color: #1b2336 !important;
                            border-bottom: 1px solid #1e293b !important;
                        }

                        .dark .fi-ta-header-cell-label {
                            color: #ffffff !important;
                        }
                    </style>
                "),
            )
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make()
                    ->navigationGroup('Akses Pengguna')
                    ->navigationSort(2),
            ]);
    }
}
