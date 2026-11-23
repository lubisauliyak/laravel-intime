<?php

namespace App\Filament\Resources\Groups\Pages;

use App\Exports\GroupImportErrorsExport;
use App\Exports\GroupTemplateExport;
use App\Filament\Resources\Groups\GroupResource;
use App\Models\Group;
use App\Models\Level;
use App\Services\Groups\GroupImportParser;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions as FormActions;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\UnorderedList;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportGroups extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $resource = GroupResource::class;

    protected string $view = 'filament.resources.groups.pages.import-groups';

    public ?array $data = [];

    /**
     * Hasil ekstraksi spreadsheet dari GroupImportParser
     */
    public array $parsedData = [];

    /**
     * Tab filter aktif untuk tabel pratinjau ('all', 'valid', 'invalid')
     */
    public string $filterTab = 'all';

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('import', Group::class) ?? false;
    }

    public function getTitle(): string
    {
        return 'Impor Data Grup';
    }

    public function getBreadcrumbs(): array
    {
        $resource = static::getResource();

        return [
            $resource::getUrl() => 'Grup',
            'Impor Data',
        ];
    }

    public function mount(): void
    {
        $this->form->fill([
            'filter_tab' => 'all',
            'allow_update_code' => true,
        ]);
    }

    protected function getHeaderActions(): array
    {
        $resource = static::getResource();

        return [
            Action::make('reset_file')
                ->label('Mulai Ulang')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->hiddenLabel()
                ->outlined()
                ->tooltip('Mulai Ulang Pemeriksaan File')
                ->visible(fn () => !empty($this->parsedData))
                ->action(function () {
                    $this->resetImportState();
                    Notification::make()
                        ->title('Form Direset')
                        ->body('Silakan pilih file spreadsheet baru untuk diperiksa.')
                        ->info()
                        ->send();
                }),

            Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->hiddenLabel()
                ->outlined()
                ->tooltip('Kembali ke Daftar Grup')
                ->url($resource::getUrl('index')),
        ];
    }

    public function resetImportState(): void
    {
        $this->parsedData = [];
        $this->filterTab = 'all';
        $this->form->fill([
            'file' => null,
            'filter_tab' => 'all',
            'allow_update_code' => true,
        ]);
        $this->resetTable();
        $this->redirect(static::getUrl());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([
                Wizard::make([
                    // Step 1: Unggah Berkas
                    Step::make('Unggah Berkas')
                        ->description('Panduan format dan pilih berkas')
                        ->icon('heroicon-m-arrow-up-tray')
                        ->completedIcon('heroicon-m-check')
                        ->schema([
                            // Section 1: Panduan & Unduh Template
                            Section::make('Panduan & Template Impor')
                                ->description('Pelajari aturan kolom data grup dan unduh berkas template resmi.')
                                ->icon('heroicon-m-information-circle')
                                ->headerActions([
                                    Action::make('download_template')
                                        ->label('Unduh Template')
                                        ->icon('heroicon-m-arrow-down-tray')
                                        ->color('info')
                                        ->outlined()
                                        ->action(function (): BinaryFileResponse {
                                            $fileName = 'Template_Import_Grup_' . now()->format('Ymd_His') . '.xlsx';
                                            return Excel::download(new GroupTemplateExport(), $fileName);
                                        }),
                                ])
                                ->schema([
                                    Text::make('Ketentuan Pengisian Template:')
                                        ->weight(FontWeight::SemiBold)
                                        ->color('gray'),

                                    UnorderedList::make([
                                        Html::make(new HtmlString('<strong>Kolom Wajib:</strong> <code>Tingkat Grup (Wajib)</code> dan <code>Nama Grup (Wajib)</code>.')),
                                        Html::make(new HtmlString('<strong>Hirarki & Urutan Baris:</strong> Baris data <em>Induk</em> harus berada di atas / sebelum baris data <em>Anak</em>.')),
                                        Html::make(new HtmlString('<strong>Kolom Kode Grup (Opsional):</strong> Maksimal 10 karakter alfanumerik. Kode harus unik di bawah Induk Grup yang sama.')),
                                        Html::make(new HtmlString('<strong>Kriteria Validasi:</strong> Hanya baris dengan data valid yang akan disimpan ke dalam database.')),
                                    ])
                                    ->columns(['default' => 1, 'sm' => 1, 'lg' => 1]),
                                ])
                                ->collapsed(),

                            // Section 2: Upload File Spreadsheet
                            Section::make('Unggah Berkas Spreadsheet')
                                ->description('Pilih file Excel (.xlsx, .xls) atau CSV (.csv). Sistem akan memeriksa keabsahan data secara instan.')
                                ->icon('heroicon-m-arrow-up-tray')
                                ->schema([
                                    FileUpload::make('file')
                                        ->label('File Spreadsheet')
                                        ->acceptedFileTypes([
                                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                            'application/vnd.ms-excel',
                                            'text/csv',
                                            'application/csv',
                                            'text/plain',
                                        ])
                                        ->maxSize(10240)
                                        ->disk('public')
                                        ->directory('imports/groups')
                                        ->live()
                                        ->afterStateUpdated(function ($state) {
                                            $this->processUploadedFile($state);
                                        }),
                                ]),
                        ])
                        ->afterValidation(function () {
                            if (empty($this->parsedData)) {
                                Notification::make()
                                    ->title('Berkas Belum Diunggah')
                                    ->body('Silakan pilih dan unggah berkas spreadsheet terlebih dahulu sebelum melanjutkan.')
                                    ->warning()
                                    ->send();

                                throw new \Filament\Support\Exceptions\Halt();
                            }
                        }),

                    // Step 2: Pratinjau & Validasi
                    Step::make('Pratinjau & Validasi')
                        ->description('Pemeriksaan data & review kesalahan')
                        ->icon('heroicon-m-table-cells')
                        ->completedIcon('heroicon-m-check')
                        ->schema([
                            // Section 3: Ringkasan Hasil Pemeriksaan (3 Stat Cards)
                            Section::make('Ringkasan Hasil Pemeriksaan')
                                ->description('Data di bawah ini dibaca langsung dari file spreadsheet tanpa mengubah database.')
                                ->icon('heroicon-m-chart-bar')
                                ->schema([
                                    Grid::make(3)->schema([
                                        Section::make('Total Data Dibaca')
                                            ->icon('heroicon-o-document-text')
                                            ->schema([
                                                Text::make(fn () => ($this->parsedData['total_rows'] ?? 0) . ' Baris')
                                                    ->size(TextSize::Large)
                                                    ->weight(FontWeight::Bold)
                                                    ->color('gray'),
                                            ])->columnSpan(1),

                                        Section::make('Siap Diimpor')
                                            ->icon('heroicon-o-check-circle')
                                            ->schema([
                                                Text::make(fn () => ($this->parsedData['valid_count'] ?? 0) . ' Data Valid')
                                                    ->size(TextSize::Large)
                                                    ->weight(FontWeight::Bold)
                                                    ->color('success'),
                                            ])->columnSpan(1),

                                        Section::make('Perlu Perbaikan')
                                            ->icon('heroicon-o-x-circle')
                                            ->schema([
                                                Text::make(fn () => ($this->parsedData['invalid_count'] ?? 0) . ' Data Bermasalah')
                                                    ->size(TextSize::Large)
                                                    ->weight(FontWeight::Bold)
                                                    ->color('danger'),
                                            ])->columnSpan(1),
                                    ]),
                                ]),

                            // Section 4: Tindakan Data Bermasalah
                            Section::make('Tindakan Data Bermasalah')
                                ->description(fn () => 'Ditemukan ' . ($this->parsedData['invalid_count'] ?? 0) . ' baris data yang belum memenuhi kriteria validasi. Baris ini dilewati pada sesi saat ini dan dapat diperbaiki secara terpisah.')
                                ->icon('heroicon-m-exclamation-triangle')
                                ->iconColor('danger')
                                ->visible(fn () => !empty($this->parsedData) && ($this->parsedData['invalid_count'] ?? 0) > 0)
                                ->schema([
                                    FormActions::make([
                                        Action::make('download_errors')
                                            ->label('Unduh Error')
                                            ->icon('heroicon-m-arrow-down-tray')
                                            ->color('danger')
                                            ->action(function (): BinaryFileResponse {
                                                $invalidRows = $this->parsedData['invalid_rows'] ?? [];

                                                if (empty($invalidRows)) {
                                                    Notification::make()
                                                        ->title('Tidak Ada Data Bermasalah')
                                                        ->body('Seluruh data saat ini valid.')
                                                        ->success()
                                                        ->send();
                                                }

                                                $fileName = 'Data_Bermasalah_Import_Grup_' . now()->format('Ymd_His') . '.csv';

                                                return Excel::download(
                                                    new GroupImportErrorsExport($invalidRows),
                                                    $fileName
                                                );
                                            }),
                                    ])->alignLeft(),
                                ]),

                            // Section: Filter Baris Pratinjau
                            ToggleButtons::make('filter_tab')
                                ->label('Filter Tampilan Pratinjau Data')
                                ->options(fn () => [
                                    'all' => 'Semua Data (' . ($this->parsedData['total_rows'] ?? 0) . ')',
                                    'valid' => 'Siap Diimpor (' . ($this->parsedData['valid_count'] ?? 0) . ')',
                                    'invalid' => 'Perlu Perbaikan (' . ($this->parsedData['invalid_count'] ?? 0) . ')',
                                ])
                                ->colors([
                                    'all' => 'gray',
                                    'valid' => 'success',
                                    'invalid' => 'danger',
                                ])
                                ->icons([
                                    'all' => 'heroicon-o-bars-3-bottom-left',
                                    'valid' => 'heroicon-o-check-circle',
                                    'invalid' => 'heroicon-o-x-circle',
                                ])
                                ->inline()
                                ->live()
                                ->afterStateUpdated(function ($state) {
                                    $this->filterTab = $state ?? 'all';
                                    $this->resetTable();
                                }),

                            // Embedded Table Preview
                            EmbeddedTable::make(),
                        ])
                        ->afterValidation(function () {
                            $validCount = $this->parsedData['valid_count'] ?? 0;
                            if ($validCount === 0) {
                                Notification::make()
                                    ->title('Tidak Ada Data Valid')
                                    ->body('Seluruh data bermasalah. Perbaiki data di spreadsheet sebelum melanjutkan ke konfirmasi.')
                                    ->danger()
                                    ->send();

                                throw new \Filament\Support\Exceptions\Halt();
                            }
                        }),

                    // Step 3: Opsi & Konfirmasi
                    Step::make('Opsi & Konfirmasi')
                        ->description('Pengaturan otomatisasi dan eksekusi')
                        ->icon('heroicon-m-check-badge')
                        ->completedIcon('heroicon-m-check')
                        ->schema([
                            // Rekapitulasi Ringkasan
                            Section::make('Rekapitulasi Data Impor')
                                ->description('Rincian grup yang siap dimasukkan ke dalam database.')
                                ->icon('heroicon-m-clipboard-document-check')
                                ->schema([
                                    Grid::make(['default' => 1, 'sm' => 2])->schema([
                                        Section::make('Grup Siap Diproses')
                                            ->icon('heroicon-o-check-circle')
                                            ->schema([
                                                Text::make(fn () => ($this->parsedData['valid_count'] ?? 0) . ' Grup Siap Disimpan')
                                                    ->size(TextSize::Large)
                                                    ->weight(FontWeight::Bold)
                                                    ->color('success'),
                                                Text::make('Grup dan hierarkinya akan disimpan ke sistem.')
                                                    ->size(TextSize::Small)
                                                    ->color('gray'),
                                            ])->columnSpan(1),

                                        Section::make('Data Dilewati')
                                            ->icon('heroicon-o-x-circle')
                                            ->schema([
                                                Text::make(fn () => ($this->parsedData['invalid_count'] ?? 0) . ' Baris Dilewati')
                                                    ->size(TextSize::Large)
                                                    ->weight(FontWeight::Bold)
                                                    ->color(fn () => ($this->parsedData['invalid_count'] ?? 0) > 0 ? 'danger' : 'gray'),
                                                Text::make(fn () => ($this->parsedData['invalid_count'] ?? 0) > 0
                                                    ? 'Baris bermasalah dilewati dan dapat diunduh di langkah sebelumnya.'
                                                    : 'Tidak ada baris yang bermasalah.')
                                                    ->size(TextSize::Small)
                                                    ->color('gray'),
                                            ])->columnSpan(1),
                                    ]),
                                ]),

                            // Opsi Tambahan Impor
                            Section::make('Opsi Tambahan Impor')
                                ->description('Sesuaikan pengaturan untuk data grup yang akan diimpor.')
                                ->icon('heroicon-m-cog-6-tooth')
                                ->schema([
                                    Grid::make(['default' => 1, 'md' => 2])->schema([
                                        Checkbox::make('allow_update_code')
                                            ->label('Perbarui Kode Grup')
                                            ->helperText('Perbarui kode jika grup sudah ada.')
                                            ->default(true),
                                    ]),
                                ]),
                        ]),
                ])
                ->skippable(false)
                ->nextAction(fn (Action $action) => $action->label('Lanjut'))
                ->previousAction(fn (Action $action) => $action->label('Kembali'))
                ->submitAction(
                    Action::make('saveImport')
                        ->label('Mulai Impor')
                        ->color('primary')
                        ->icon('heroicon-m-check')
                        ->extraAttributes([
                            'wire:confirm' => 'Apakah Anda yakin ingin mulai mengimpor data?',
                        ])
                        ->action('saveImport')
                ),
            ]);
    }

    /**
     * Memproses file yang baru diunggah dan menjalankan validasi in-memory
     */
    public function processUploadedFile($state): void
    {
        if (empty($state)) {
            $this->parsedData = [];
            $this->resetTable();
            return;
        }

        try {
            $filePath = null;
            if (is_string($state)) {
                $filePath = storage_path('app/public/' . $state);
            } elseif (is_object($state) && method_exists($state, 'getRealPath')) {
                $filePath = $state->getRealPath();
            }

            if (!$filePath || !file_exists($filePath)) {
                $filePath = Storage::disk('public')->path($state);
            }

            if (!file_exists($filePath)) {
                throw new \Exception("Berkas spreadsheet tidak ditemukan di penyimpanan: {$filePath}");
            }

            $parser = new GroupImportParser(auth()->user());
            $this->parsedData = $parser->parse($filePath);

            // UX Otomatis: jika ada error, langsung aktifkan tab 'invalid'
            if (($this->parsedData['invalid_count'] ?? 0) > 0) {
                $this->filterTab = 'invalid';
                $this->data['filter_tab'] = 'invalid';
            } else {
                $this->filterTab = 'all';
                $this->data['filter_tab'] = 'all';
            }

            $totalCount = $this->parsedData['total_rows'] ?? 0;
            $validCount = $this->parsedData['valid_count'] ?? 0;
            $invalidCount = $this->parsedData['invalid_count'] ?? 0;

            if ($totalCount === 0) {
                Notification::make()
                    ->title('Berkas Kosong')
                    ->body('Berkas spreadsheet tidak memuat baris data grup untuk diproses.')
                    ->info()
                    ->send();
            } elseif ($invalidCount > 0) {
                Notification::make()
                    ->title('Pemeriksaan Selesai')
                    ->body("Ditemukan {$validCount} data grup siap diimpor dan {$invalidCount} data bermasalah. Silakan periksa rinciannya pada tabel di bawah.")
                    ->warning()
                    ->send();
            } else {
                Notification::make()
                    ->title('Pemeriksaan Selesai')
                    ->body("Semua {$validCount} data grup valid dan siap untuk disimpan.")
                    ->success()
                    ->send();
            }

            $this->resetTable();
        } catch (\Throwable $e) {
            $this->parsedData = [];
            Notification::make()
                ->title('Gagal Membaca File')
                ->body('Terjadi kesalahan saat memproses berkas: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Mengambil baris data yang disaring sesuai tab yang dipilih
     */
    public function getFilteredRecords(): array
    {
        if (empty($this->parsedData)) {
            return [];
        }

        $activeTab = $this->data['filter_tab'] ?? $this->filterTab ?? 'all';

        return match ($activeTab) {
            'valid' => $this->parsedData['valid_rows'] ?? [],
            'invalid' => $this->parsedData['invalid_rows'] ?? [],
            default => $this->parsedData['all_rows'] ?? [],
        };
    }

    /**
     * Konfigurasi tabel pratinjau (Murni Read-Only)
     */
    public function table(Table $table): Table
    {
        return $table
            ->heading('Pratinjau Data Impor (Read-Only)')
            ->description('Tabel ini murni untuk inspeksi visual. Hanya data berstatus hijau (valid) yang dapat disimpan.')
            ->records(fn () => $this->getFilteredRecords())
            ->headerActions([
                Action::make('download_errors_table')
                    ->label('Unduh Error')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('danger')
                    ->visible(fn () => ($this->parsedData['invalid_count'] ?? 0) > 0)
                    ->action(function (): BinaryFileResponse {
                        $invalidRows = $this->parsedData['invalid_rows'] ?? [];
                        $fileName = 'Data_Bermasalah_Import_Grup_' . now()->format('Ymd_His') . '.csv';

                        return Excel::download(
                            new GroupImportErrorsExport($invalidRows),
                            $fileName
                        );
                    }),
            ])
            ->columns([
                TextColumn::make('excel_row')
                    ->label('Baris')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('is_valid')
                    ->label('Status Data')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => $state ? 'Siap Diimpor' : 'Perlu Perbaikan')
                    ->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),

                TextColumn::make('tingkat_grup')
                    ->label('Tingkat Grup')
                    ->badge()
                    ->color('info')
                    ->placeholder('-'),

                TextColumn::make('nama_grup')
                    ->label('Nama Grup')
                    ->weight(FontWeight::SemiBold)
                    ->searchable(),

                TextColumn::make('induk_grup')
                    ->label('Induk Grup')
                    ->badge()
                    ->color('gray')
                    ->placeholder('-'),

                TextColumn::make('kode_grup')
                    ->label('Kode Grup')
                    ->badge()
                    ->color(fn ($state) => filled($state) ? 'primary' : 'gray')
                    ->placeholder('-'),

                TextColumn::make('error_message')
                    ->label('Keterangan Masalah')
                    ->color('danger')
                    ->weight(FontWeight::Bold)
                    ->wrap()
                    ->placeholder('-'),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    /**
     * Eksekusi penyimpanan data valid ke database dalam blok DB::transaction
     */
    public function saveImport(): void
    {
        $validRows = $this->parsedData['valid_rows'] ?? [];

        if (empty($validRows)) {
            Notification::make()
                ->title('Gagal Menyimpan Data')
                ->body('Tidak ada baris data valid yang dapat disimpan.')
                ->danger()
                ->send();
            return;
        }

        $allowUpdateCode = $this->data['allow_update_code'] ?? true;

        try {
            DB::beginTransaction();

            $insertedCount = 0;
            $updatedCount = 0;
            $createdGroupIds = [];

            $user = auth()->user();
            $isSuperAdmin = $user ? $user->isSuperAdmin() : true;
            $manageableIds = $user ? $user->getManageableGroupIds() : [];

            // Cache lokal untuk nama level -> id
            $levelMap = Level::all()->pluck('id', 'name')->mapWithKeys(function ($id, $name) {
                return [strtoupper(trim($name)) => $id];
            })->toArray();

            // Cache lokal untuk nama grup eksisting -> id (hanya dalam scope user)
            $groupQuery = Group::query();
            if (!$isSuperAdmin) {
                $groupQuery->whereIn('id', $manageableIds);
            }
            $groupMap = $groupQuery->pluck('id', 'name')->mapWithKeys(function ($id, $name) {
                return [strtoupper(trim($name)) => $id];
            })->toArray();

            foreach ($validRows as $row) {
                $groupName = strtoupper(trim($row['nama_grup']));
                $tingkatName = strtoupper(trim($row['tingkat_grup']));
                $indukName = !empty($row['induk_grup']) ? strtoupper(trim($row['induk_grup'])) : null;
                $groupCode = !empty($row['kode_grup']) ? strtoupper(trim((string) $row['kode_grup'])) : null;

                $levelId = $row['level_id'] ?? ($levelMap[$tingkatName] ?? null);
                if (!$levelId) {
                    $level = Level::whereRaw('UPPER(name) = ?', [$tingkatName])->first();
                    $levelId = $level?->id;
                    if ($levelId) {
                        $levelMap[$tingkatName] = $levelId;
                    }
                }

                $parentId = null;
                if ($indukName) {
                    $parentId = $createdGroupIds[$indukName] ?? ($row['parent_id'] ?? ($groupMap[$indukName] ?? null));
                    if (!$parentId) {
                        $parentGroupQuery = Group::whereRaw('UPPER(name) = ?', [$indukName]);
                        if (!$isSuperAdmin) {
                            $parentGroupQuery->whereIn('id', $manageableIds);
                        }
                        $parentGroup = $parentGroupQuery->first();
                        $parentId = $parentGroup?->id;
                        if ($parentId) {
                            $groupMap[$indukName] = $parentId;
                        }
                    }

                    if (!$parentId && !$isSuperAdmin) {
                        throw new \Exception("Induk grup '{$indukName}' tidak ditemukan dalam wilayah wewenang Anda.");
                    }
                }

                // Cek apakah grup sudah ada dengan nama, level, dan parent yang sama
                $existingGroup = Group::where('name', $groupName)
                    ->where('level_id', $levelId)
                    ->where('parent_id', $parentId)
                    ->first();

                if ($existingGroup) {
                    // Update kode jika diizinkan dan kode diisi
                    if ($groupCode && $allowUpdateCode) {
                        if ($existingGroup->code !== $groupCode) {
                            $existingGroup->update(['code' => $groupCode]);
                            $updatedCount++;
                        }
                    }
                    $createdGroupIds[$groupName] = $existingGroup->id;
                    $groupMap[$groupName] = $existingGroup->id;
                    continue;
                }

                // Safety net: cek apakah ada grup dengan nama dan level yang sama di bawah parent lain dalam scope
                $conflictGroupQuery = Group::where('name', $groupName)
                    ->where('level_id', $levelId)
                    ->where('parent_id', '!=', $parentId);

                if (!$isSuperAdmin) {
                    $conflictGroupQuery->whereIn('id', $manageableIds);
                }

                if ($conflictGroupQuery->exists()) {
                    throw new \Exception("Konflik data: Grup '{$groupName}' sudah terdaftar pada tingkat yang sama di bawah induk lain dalam wilayah wewenang Anda.");
                }

                $newGroup = new Group([
                    'name' => $groupName,
                    'code' => $groupCode,
                    'level_id' => $levelId,
                    'parent_id' => $parentId,
                    'status' => true,
                ]);
                $newGroup->save();

                $createdGroupIds[$groupName] = $newGroup->id;
                $groupMap[$groupName] = $newGroup->id;
                $insertedCount++;
            }

            DB::commit();

            // Bersihkan file spreadsheet sementara yang diunggah
            $file = $this->data['file'] ?? null;
            if ($file && is_string($file)) {
                Storage::disk('public')->delete($file);
            }

            // Bersihkan cache terkait master grup dan level
            Cache::forget('import_group_cache');
            Cache::forget('import_level_cache');
            Cache::forget('import_group_cache_validator');
            Cache::forget('import_level_cache_validator');
            Cache::forget('member_import_group_map');

            $userId = auth()->id();
            if ($userId) {
                Cache::forget("import_group_cache_u{$userId}");
                Cache::forget("import_level_cache_u{$userId}");
            }
            Cache::forget('import_group_cache_public');
            Cache::forget('import_level_cache_public');

            $summary = [];
            if ($insertedCount > 0) {
                $summary[] = "{$insertedCount} grup baru ditambahkan";
            }
            if ($updatedCount > 0) {
                $summary[] = "{$updatedCount} kode grup diperbarui";
            }
            if ($insertedCount === 0 && $updatedCount === 0) {
                $summary[] = "Seluruh grup sudah terdaftar sebelumnya";
            }

            Notification::make()
                ->title('Impor Grup Berhasil')
                ->body(implode(', ', $summary) . '.')
                ->success()
                ->send();

            $this->redirect(GroupResource::getUrl('index'));
        } catch (\Throwable $e) {
            DB::rollBack();

            Notification::make()
                ->title('Gagal Menyimpan Data')
                ->body('Terjadi kesalahan database saat menyimpan grup: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
