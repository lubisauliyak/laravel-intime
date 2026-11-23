<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Models\AgeGroup;
use App\Models\Group;
use App\Models\PositionCategory;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([

                // ── Section 1: Data Pribadi ──────────────────────────────
                Section::make('Data Pribadi')
                    ->icon('heroicon-o-user')
                    ->description('Informasi identitas dasar anggota.')
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('member_code')
                            ->label('ID Anggota')
                            ->nullable()
                            ->unique(ignoreRecord: true)
                            ->readOnly(fn ($livewire) => method_exists($livewire, 'isMemberCodeLocked') ? $livewire->isMemberCodeLocked() : false)
                            ->dehydrated(true)
                            ->prefixAction(function ($livewire, Set $set) {
                                if ($livewire instanceof CreateMember) {
                                    return Action::make('toggleLock')
                                        ->icon(fn () => $livewire->isMemberCodeLocked() ? 'heroicon-m-lock-closed' : 'heroicon-m-lock-open')
                                        ->color(fn () => $livewire->isMemberCodeLocked() ? 'gray' : 'primary')
                                        ->tooltip(fn () => $livewire->isMemberCodeLocked() ? 'ID otomatis terkunci. Klik untuk ubah manual.' : 'Klik untuk mengunci kembali ke ID otomatis.')
                                        ->action(fn () => $livewire->toggleMemberCodeLock($set));
                                }

                                if ($livewire instanceof EditMember) {
                                    return Action::make('lockIndicator')
                                        ->icon(fn () => $livewire->isMemberCodeLocked() ? 'heroicon-m-lock-closed' : 'heroicon-m-lock-open')
                                        ->color(fn () => $livewire->isMemberCodeLocked() ? 'gray' : 'warning')
                                        ->tooltip(fn () => $livewire->isMemberCodeLocked()
                                            ? 'ID Anggota terkunci demi keamanan data KTA & QR Code.'
                                            : 'Mode input manual aktif. Anda dapat mengubah ID secara langsung.');
                                }

                                return null;
                            })
                            ->suffixAction(function ($livewire, Get $get) {
                                if (! ($livewire instanceof EditMember)) {
                                    return null;
                                }

                                $currentCode = $get('member_code');
                                $hasExistingCode = ! empty($currentCode) || ! empty($livewire->initialMemberCode);

                                // 1. Mode manual aktif: Sediakan aksi "Batal" untuk kembalikan ke ID asli & kunci kembali
                                if (! $livewire->isMemberCodeLocked()) {
                                    return Action::make('revertMemberCode')
                                        ->label('Batal')
                                        ->icon('heroicon-m-arrow-uturn-left')
                                        ->color('gray')
                                        ->tooltip('Batalkan mode manual dan kembalikan ke ID awal')
                                        ->action(function (Set $set) use ($livewire) {
                                            $livewire->revertMemberCode($set);
                                            Notification::make()
                                                ->info()
                                                ->title('Mode Manual Dibatalkan')
                                                ->body('ID Anggota dikembalikan ke semula dan dikunci kembali.')
                                                ->send();
                                        });
                                }

                                // 2. Sudah punya ID: Sediakan tombol "Ubah ID" dengan modal konfirmasi peringatan bahaya
                                if ($hasExistingCode) {
                                    return Action::make('changeMemberCode')
                                        ->label('Ubah ID')
                                        ->icon('heroicon-m-arrow-path')
                                        ->color('warning')
                                        ->tooltip('Ubah atau generate ulang ID Anggota')
                                        ->requiresConfirmation()
                                        ->modalHeading('Konfirmasi Perubahan ID Anggota')
                                        ->modalDescription(fn () => "PERINGATAN: Mengubah ID Anggota ({$currentCode}) berdampak langsung pada fisik Kartu Anggota (KTA) dan QR Code yang sudah dicetak! QR Code lama akan dihapus dan tidak lagi valid untuk presensi.")
                                        ->modalIcon('heroicon-o-exclamation-triangle')
                                        ->modalIconColor('danger')
                                        ->modalSubmitActionLabel('Lanjutkan Perubahan')
                                        ->form([
                                            Radio::make('change_mode')
                                                ->label('Metode Perubahan')
                                                ->options([
                                                    'auto' => 'Generate ID Baru Otomatis (sesuai kelompok terpilih)',
                                                    'manual' => 'Input Manual (buka kunci field ID untuk diedit langsung)',
                                                ])
                                                ->default('auto')
                                                ->required(),
                                            Checkbox::make('agree')
                                                ->label('Saya memahami konsekuensi ini dan bertanggung jawab atas perubahan ID serta pencetakan ulang KTA fisik.')
                                                ->required(),
                                        ])
                                        ->action(function (array $data, Set $set, Get $get) use ($livewire) {
                                            if ($data['change_mode'] === 'auto') {
                                                $groupId = $get('group_id');
                                                $newCode = $livewire->autoFillMemberCode($groupId ? (int) $groupId : null, $set);
                                                if ($newCode) {
                                                    Notification::make()
                                                        ->warning()
                                                        ->title('ID Anggota Diperbarui')
                                                        ->body("ID baru berhasil digenerate: {$newCode}. Klik 'Simpan Perubahan' di bawah untuk menerapkan.")
                                                        ->send();
                                                } else {
                                                    Notification::make()
                                                        ->danger()
                                                        ->title('Gagal Generate ID')
                                                        ->body('Kelompok belum memiliki prefix/kode yang valid untuk ID otomatis.')
                                                        ->send();
                                                }
                                            } else {
                                                $livewire->unlockMemberCode();
                                                Notification::make()
                                                    ->info()
                                                    ->title('Mode Manual Dibuka')
                                                    ->body('Kunci ID Anggota telah dibuka. Anda dapat mengedit field ID Anggota secara manual.')
                                                    ->send();
                                            }
                                        });
                                }

                                // 3. Belum punya ID: Sediakan tombol langsung generate ID tanpa modal berbahaya
                                return Action::make('generateMemberCode')
                                    ->label('Generate ID')
                                    ->icon('heroicon-m-sparkles')
                                    ->color('primary')
                                    ->tooltip('Generate ID Anggota otomatis berdasarkan kelompok')
                                    ->action(function (Set $set, Get $get) use ($livewire) {
                                        $groupId = $get('group_id');
                                        $newCode = $livewire->autoFillMemberCode($groupId ? (int) $groupId : null, $set);
                                        if ($newCode) {
                                            Notification::make()
                                                ->success()
                                                ->title('ID Anggota Berhasil Digenerate')
                                                ->body("ID baru: {$newCode}")
                                                ->send();
                                        } else {
                                            Notification::make()
                                                ->warning()
                                                ->title('Pilih Kelompok Terlebih Dahulu')
                                                ->body('Pilih kelompok yang valid terlebih dahulu untuk membuat ID otomatis.')
                                                ->send();
                                        }
                                    });
                            })
                            ->placeholder(function (Get $get) {
                                $groupId = $get('group_id');
                                if (! $groupId) {
                                    return 'Pilih kelompok terlebih dahulu';
                                }
                                $group = Group::find($groupId);
                                if (! $group) {
                                    return 'ID Otomatis';
                                }
                                $val = app(\App\Services\Groups\GroupHierarchyService::class)->validateChainIntegrity($group);
                                if ($val['status'] === 'no_code' || ! $val['is_valid']) {
                                    return '(Opsional) Input Manual';
                                }

                                return "Otomatis: {$val['prefix']}XXX";
                            })
                            ->helperText(function (Get $get, $livewire) {
                                $groupId = $get('group_id');
                                if (! $groupId) {
                                    return 'Pilih kelompok terlebih dahulu untuk membuat ID otomatis.';
                                }
                                $group = Group::find($groupId);
                                if (! $group) {
                                    return null;
                                }
                                $val = app(\App\Services\Groups\GroupHierarchyService::class)->validateChainIntegrity($group);
                                if ($val['status'] === 'no_code') {
                                    return '⚠️ Kelompok ini belum memiliki kode untuk generate ID otomatis.';
                                }
                                if (! $val['is_valid']) {
                                    return "⚠️ {$val['message']}";
                                }

                                if ($livewire instanceof CreateMember) {
                                    if ($livewire->isMemberCodeLocked()) {
                                        return 'ID digenerate otomatis berurutan.';
                                    }

                                    return 'Mode input manual aktif. Anda dapat menentukan ID Khusus.';
                                }

                                if ($livewire instanceof EditMember) {
                                    $currentCode = $get('member_code');
                                    if (! $livewire->isMemberCodeLocked()) {
                                        return '⚠️ Mode input manual aktif. Anda dapat menyesuaikan ID secara manual, atau klik tombol "Batal" di kanan input untuk mengunci kembali.';
                                    }

                                    if (empty($currentCode)) {
                                        return 'Anggota ini belum memiliki ID. Klik tombol "Generate ID" di kanan input untuk membuat ID otomatis.';
                                    }

                                    if (! empty($val['prefix']) && ! str_starts_with($currentCode, $val['prefix'])) {
                                        return "⚠️ ID saat ini ({$currentCode}) berbeda dengan prefix kelompok baru ({$val['prefix']}). ID lama dipertahankan demi keamanan KTA fisik. Klik 'Ubah ID' jika ingin memperbarui ke prefix baru.";
                                    }

                                    return "Prefix kelompok: {$val['prefix']}. ID Anggota terkunci untuk keamanan KTA & presensi.";
                                }

                                return "Prefix kelompok: {$val['prefix']}. ID Anggota dapat disesuaikan manual.";
                            })
                            ->mutateDehydratedStateUsing(fn ($state) => $state ? strtoupper($state) : null)
                            ->validationMessages([
                                'unique' => 'ID Anggota ini sudah terdaftar. Silakan gunakan ID lain atau klik ikon gembok untuk nomor baru.',
                            ]),

                        Select::make('group_id')
                            ->label('Kelompok')
                            ->relationship(
                                name: 'group',
                                titleAttribute: 'groups.name',
                                modifyQueryUsing: function ($query) {
                                    $user = auth()->user();
                                    $query->whereHas('level', fn ($q) => $q->where('level_number', 1))
                                        ->leftJoin('groups as desa', 'groups.parent_id', '=', 'desa.id')
                                        ->select('groups.*')
                                        ->orderBy('desa.name', 'asc')
                                        ->orderBy('groups.name', 'asc');

                                    if ($user && ! $user->isSuperAdmin() && $user->group_id) {
                                        $descendantIds = $user->group->getAllDescendantIds();
                                        $query->whereIn('groups.id', $descendantIds);
                                    } elseif ($user && ! $user->isSuperAdmin() && ! $user->group_id) {
                                        $query->whereRaw('1 = 0');
                                    }
                                }
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state, $livewire) {
                                if ($livewire instanceof CreateMember) {
                                    $livewire->autoFillMemberCode($state ? (int) $state : null, $set);
                                } elseif ($livewire instanceof EditMember) {
                                    // Hanya auto-fill jika anggota belum memiliki ID sama sekali
                                    if (empty($get('member_code'))) {
                                        $livewire->autoFillMemberCode($state ? (int) $state : null, $set);
                                    }
                                }
                            }),

                        TextInput::make('full_name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->mutateDehydratedStateUsing(fn ($state) => $state ? strtoupper($state) : null),

                        TextInput::make('nick_name')
                            ->label('Nama Panggilan')
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->mutateDehydratedStateUsing(fn ($state) => $state ? strtoupper($state) : null),

                        Select::make('gender')
                            ->label('Jenis Kelamin')
                            ->options(\App\Enums\Gender::class)
                            ->required(),

                        DatePicker::make('birth_date')
                            ->label('Tanggal Lahir')
                            ->displayFormat('d/m/Y')
                            ->format('Y-m-d')
                            ->placeholder('dd/mm/yyyy')
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if (! $state) {
                                    return;
                                }

                                $birthDate = Carbon::parse($state);
                                $age = $birthDate->age;
                                $set('age', $age);

                                $matchingGroup = AgeGroup::where('min_age', '<=', $age)
                                    ->where(function ($query) use ($age) {
                                        $query->where('max_age', '>=', $age)
                                            ->orWhereNull('max_age');
                                    })
                                    ->first();

                                if ($matchingGroup) {
                                    $set('age_group_id', $matchingGroup->id);
                                }
                            }),

                        TextInput::make('age')
                            ->label('Usia Saat Ini')
                            ->suffix(' Tahun')
                            ->numeric()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if (! $state) {
                                    return;
                                }

                                $age = (int) $state;

                                $matchingGroup = AgeGroup::where('min_age', '<=', $age)
                                    ->where(function ($query) use ($age) {
                                        $query->where('max_age', '>=', $age)
                                            ->orWhereNull('max_age');
                                    })
                                    ->first();

                                if ($matchingGroup) {
                                    $set('age_group_id', $matchingGroup->id);
                                }
                            }),

                        Select::make('age_group_id')
                            ->label('Kategori Usia')
                            ->relationship('ageGroup', 'name', fn ($query) => $query->orderBy('sort_order'))
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->helperText('Kategori dipilih otomatis berdasarkan usia, namun tetap bisa diubah manual jika diperlukan.'),

                        Toggle::make('is_permanent')
                            ->label('Status Persetujuan (Permanen)')
                            ->onColor('primary')
                            ->offColor('warning')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Aktifkan jika keanggotaan telah diverifikasi dan disetujui admin. Pendaftaran mandiri bernilai belum disetujui sampai diverifikasi.'),

                        Toggle::make('status')
                            ->label('Target Kehadiran (Status Aktif)')
                            ->onColor('success')
                            ->offColor('gray')
                            ->default(true)
                            ->required()
                            ->inline(false)
                            ->helperText('Jika aktif, anggota menjadi target kehadiran wajib dan dihitung alpa bila tidak hadir. Jika nonaktif, anggota tetap dapat hadir/izin namun tidak dibebani target kuota dan tidak dihitung alpa.'),

                        Select::make('membership_type')
                            ->label('Kepengurusan')
                            ->options(\App\Enums\MembershipType::class)
                            ->required()
                            ->live()
                            ->default(\App\Enums\MembershipType::ANGGOTA),
                    ]),

                // ── Section 4: Struktur Kepengurusan ─────────────────────
                Section::make('Struktur Kepengurusan')
                    ->icon('heroicon-o-building-office')
                    ->description('Kelola posisi pengurus anggota dalam struktur organisasi.')
                    ->visible(fn ($get) => $get('membership_type') === \App\Enums\MembershipType::PENGURUS->value || $get('membership_type') === \App\Enums\MembershipType::PENGURUS)
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('positions')
                            ->relationship('positions')
                            ->label('Pengurus')
                            ->addActionLabel('Tambah Pengurus')
                            ->schema([
                                Select::make('position_category_id')
                                    ->label('Kategori')
                                    ->relationship('category', 'name', modifyQueryUsing: fn ($query) => $query->orderBy('sort_order')->orderBy('name'))
                                    ->required()
                                    ->searchable()
                                    ->preload(),

                                Select::make('group_id')
                                    ->label('Grup')
                                    ->relationship(
                                        name: 'group',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function ($query) {
                                            $user = auth()->user();
                                            $query->with('level')->orderBy('level_id');

                                            if ($user && ! $user->isSuperAdmin() && $user->group_id) {
                                                $descendantIds = $user->group->getAllDescendantIds();
                                                $query->whereIn('groups.id', $descendantIds);
                                            } elseif ($user && ! $user->isSuperAdmin() && ! $user->group_id) {
                                                $query->whereRaw('1 = 0');
                                            }
                                        }
                                    )
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                                    ->required()
                                    ->searchable()
                                    ->preload(),

                                TextInput::make('position_name')
                                    ->label('Nama Pengurus')
                                    ->placeholder('CONTOH: KETUA')
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                    ->mutateDehydratedStateUsing(fn ($state) => $state ? strtoupper($state) : null),

                                TextInput::make('sort_order')
                                    ->label('Urutan')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->collapsible()
                            ->itemLabel(function (array $state): string {
                                $positionName = $state['position_name'] ?? '';
                                $categoryName = '';
                                $levelName = '';

                                // Get category name
                                if (! empty($state['position_category_id'])) {
                                    $category = PositionCategory::find($state['position_category_id']);
                                    if ($category) {
                                        $categoryName = $category->name;
                                    }
                                }

                                // Get level name from group
                                if (! empty($state['group_id'])) {
                                    $group = Group::find($state['group_id']);
                                    if ($group && $group->level) {
                                        $levelName = $group->level->name;
                                    }
                                }

                                $parts = array_filter([$positionName, $categoryName, $levelName]);

                                return ! empty($parts) ? implode(' ', $parts) : 'Pengurus';
                            }),
                    ]),

                // ── Section 2: Foto ──────────────────────────────────────
                Section::make('Foto')
                    ->icon('heroicon-o-camera')
                    ->columnSpanFull()
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        FileUpload::make('profile_photo_path')
                            ->label('Foto Profil')
                            ->image()
                            ->imageEditor()
                            ->imageCropAspectRatio('1:1')
                            ->imageResizeTargetWidth('400')
                            ->imageResizeTargetHeight('400')
                            ->directory('member-photos/profile')
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->helperText('Foto wajah. Maks 2MB.'),

                        FileUpload::make('membership_card_photo_path')
                            ->label('Kartu Anggota')
                            ->image()
                            ->imageEditor()
                            ->directory('member-photos/membership-card')
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->helperText('Foto Kartu Tanda Anggota. Maks 2MB.'),
                    ]),

            ]);
    }
}
