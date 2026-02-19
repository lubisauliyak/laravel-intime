<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Lengkap')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Alamat Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->validationMessages([
                        'unique' => 'Alamat email ini sudah terdaftar. Silakan gunakan email lain.',
                    ]),
                TextInput::make('password')
                    ->label('Kata Sandi')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Biarkan kosong jika tidak ingin mengubah kata sandi' : 'Gunakan kombinasi huruf dan angka yang kuat')
                    ->maxLength(255),
                Select::make('group_id')
                    ->relationship(
                        name: 'group',
                        titleAttribute: 'groups.name',
                        modifyQueryUsing: function (Builder $query) {
                            $user = auth()->user();
                            
                            $query->where('groups.status', true)
                                ->leftJoin('groups as parents', 'groups.parent_id', '=', 'parents.id')
                                ->join('levels', 'groups.level_id', '=', 'levels.id')
                                ->select('groups.*')
                                ->orderBy('levels.level_number', 'desc')
                                ->orderBy('parents.name', 'asc')
                                ->orderBy('groups.name', 'asc');
                            
                            // Super Admin can see all active groups
                            if ($user->isSuperAdmin()) {
                                return $query;
                            }
                            
                            // Admin/Operator can only see their own group or groups below them
                            if ($user->group_id) {
                                $descendantIds = $user->group->getAllDescendantIds();
                                return $query->whereIn('groups.id', $descendantIds);
                            }
                            
                            // If user has no group and not super admin, show no groups
                            return $query->whereRaw('1 = 0');
                        }
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->label('Grup')
                    ->placeholder('Pilih grup')
                    ->searchable()
                    ->preload()
                    ->validationMessages([
                        'required' => 'Grup wajib diisi untuk Admin dan Operator.',
                    ])
                    ->required(fn (Get $get) => in_array($get('role'), ['admin', 'operator'])),
                Select::make('role')
                    ->label('Hak Akses (Peran)')
                    ->options(function () {
                        $user = auth()->user();
                        $superAdminRole = config('filament-shield.super_admin.name', 'super_admin');

                        $query = Role::query();

                        if (!$user->isSuperAdmin()) {
                            $query->where('name', '!=', $superAdminRole);
                        }

                        return $query->pluck('name', 'name')
                            ->mapWithKeys(fn ($name) => [$name => strtoupper(str_replace('_', ' ', $name))])
                            ->toArray();
                    })
                    ->disabled(fn () => auth()->user()->isOperator())
                    ->dehydrated()
                    ->helperText('Superadmin memiliki akses penuh, Admin untuk manajemen, dan Operator untuk input data.')
                    ->default('operator')
                    ->required()
                    ->live(),
                Toggle::make('status')
                    ->label('Status Akun Aktif')
                    ->onColor('success')
                    ->offColor('danger')
                    ->required()
                    ->default(true),
            ]);
    }
}
