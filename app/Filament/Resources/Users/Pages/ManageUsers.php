<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Pengguna')
                ->modalHeading('Tambah Pengguna Baru')
                ->after(function ($record) {
                    // Assign Spatie role based on the 'role' column
                    if ($record->role) {
                        $record->syncRoles([$record->role]);
                    }
                    
                    // Set email as verified so user can login
                    if (!$record->email_verified_at) {
                        $record->update(['email_verified_at' => now()]);
                    }
                }),
        ];
    }
}
