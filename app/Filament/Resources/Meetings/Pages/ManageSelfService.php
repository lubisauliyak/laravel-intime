<?php

namespace App\Filament\Resources\Meetings\Pages;

use App\Filament\Resources\Meetings\MeetingResource;
use App\Models\Meeting;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ManageSelfService extends ViewRecord
{
    protected static string $resource = MeetingResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;
        if (is_string($record) || is_int($record)) {
            $record = Meeting::where('uid', $record)->orWhere('id', $record)->first();
        }

        if ($record instanceof Meeting) {
            return auth()->user()?->can('manageSelfService', $record) ?? false;
        }

        return auth()->user()?->can('create', Meeting::class) ?? false;
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-text';
    }

    public static function getNavigationLabel(): string
    {
        return 'Pengaturan Mandiri';
    }

    public function getTitle(): string
    {
        return 'Pengaturan Mandiri';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit_self_service')
                ->label('Ubah')
                ->url(fn () => MeetingResource::getUrl('edit-self-service', [$this->getRecord()])),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Presensi Mandiri')
                    ->description('Status akses dan tautan presensi mandiri.')
                    ->schema([
                        IconEntry::make('is_presence_enabled')
                            ->label('Akses Presensi Mandiri')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                        TextEntry::make('self_presence_url')
                            ->label('URL Presensi Mandiri')
                            ->state(fn ($record) => $record->is_presence_enabled && ! $record->isExpired() ? url("/self-presence/{$record->uid}") : null)
                            ->copyable(fn ($state) => $state !== null)
                            ->icon(fn ($state) => $state !== null ? 'heroicon-m-link' : null)
                            ->color(fn ($state) => $state !== null ? 'info' : 'gray')
                            ->placeholder('Tidak tersedia'),
                    ])
                    ->columnSpanFull()
                    ->collapsed(),
                Section::make('Perizinan Mandiri')
                    ->description('Status akses, tautan, dan konten form perizinan mandiri.')
                    ->schema([
                        IconEntry::make('is_permission_enabled')
                            ->label('Akses Perizinan Mandiri')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                        TextEntry::make('self_perizinan_url')
                            ->label('URL Perizinan Mandiri')
                            ->state(fn ($record) => $record->is_permission_enabled && ! $record->isExpired() ? url("/self-permission/{$record->uid}") : null)
                            ->copyable(fn ($state) => $state !== null)
                            ->icon(fn ($state) => $state !== null ? 'heroicon-m-link' : null)
                            ->color(fn ($state) => $state !== null ? 'info' : 'gray')
                            ->placeholder('Tidak tersedia'),
                        IconEntry::make('is_permission_template_active')
                            ->label('Template Surat')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                        TextEntry::make('permission_opening_text')
                            ->label('Teks Pembuka')
                            ->placeholder('Tidak ada teks pembuka.')
                            ->html()
                            ->visible(fn ($record) => $record->is_permission_template_active)
                            ->columnSpanFull()
                            ->prose(),
                        TextEntry::make('permission_statement_text')
                            ->label('Teks Pernyataan')
                            ->placeholder('Tidak ada teks pernyataan.')
                            ->html()
                            ->visible(fn ($record) => $record->is_permission_template_active)
                            ->columnSpanFull()
                            ->prose(),
                        TextEntry::make('permission_closing_text')
                            ->label('Teks Penutup')
                            ->placeholder('Tidak ada teks penutup.')
                            ->html()
                            ->visible(fn ($record) => $record->is_permission_template_active)
                            ->columnSpanFull()
                            ->prose(),
                    ])
                    ->columnSpanFull()
                    ->collapsed(),
            ]);
    }
}
