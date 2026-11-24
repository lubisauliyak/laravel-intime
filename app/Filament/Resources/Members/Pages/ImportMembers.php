<?php

namespace App\Filament\Resources\Members\Pages;

use App\Exports\MemberImportErrorsExport;
use App\Exports\MemberTemplateExport;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Member;
use App\Services\Members\MemberImportParser;
use Carbon\Carbon;
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

class ImportMembers extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $resource = MemberResource::class;

    protected string $view = 'filament.resources.members.pages.import-members';

    public ?array $data = [];

    /**
     * Hasil ekstraksi spreadsheet dari MemberImportParser
     */
    public array $parsedData = [];

    /**
     * Tab filter aktif untuk tabel pratinjau ('all', 'valid', 'invalid')
     */
    public string $filterTab = 'all';

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('import', Member::class) ?? false;
    }

    public function getTitle(): string
    {
        return 'Impor Data Anggota';
    }

    public function getBreadcrumbs(): array
    {
        $resource = static::getResource();

        return [
            $resource::getUrl() => 'Anggota',
            'Impor Data',
        ];
    }

    public function mount(): void
    {
        $this->form->fill([
            'filter_tab' => 'all',
            'auto_generate_kta' => true,
            'is_permanent' => true,
            'status' => true,
            'allow_update' => true,
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
                ->visible(fn () => ! empty($this->parsedData))
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
                ->tooltip('Kembali ke Daftar Anggota')
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
            'auto_generate_kta' => true,
            'is_permanent' => true,
            'status' => true,
            'allow_update' => true,
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
                                ->description('Pelajari aturan kolom data anggota dan unduh berkas template resmi.')
                                ->icon('heroicon-m-information-circle')
                                ->headerActions([
                                    Action::make('download_template')
                                        ->label('Unduh Template')
                                        ->icon('heroicon-m-arrow-down-tray')
                                        ->color('info')
                                        ->outlined()
                                        ->action(function (): BinaryFileResponse {
                                            $fileName = 'Template_Import_Anggota_'.now()->format('Ymd_His').'.xlsx';

                                            return Excel::download(new MemberTemplateExport, $fileName);
                                        }),
                                ])
                                ->schema([
                                    Text::make('Ketentuan Pengisian Template:')
                                        ->weight(FontWeight::SemiBold)
                                        ->color('gray'),

                                    UnorderedList::make([
                                        Html::make(new HtmlString('<strong>Kolom Wajib:</strong> Nama Lengkap, Kelompok, dan L/P (Jenis Kelamin: <code>L</code> atau <code>P</code>).')),
                                        Html::make(new HtmlString('<strong>Kolom ID Anggota:</strong> Kosongkan untuk menambah anggota baru (dibuat otomatis oleh sistem), atau isi kode lama untuk memperbarui data.')),
                                        Html::make(new HtmlString('<strong>Kolom Tanggal Lahir:</strong> Gunakan format <code>DD/MM/YYYY</code> (contoh: <code>15/01/1990</code>).')),
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
                                        ->directory('imports')
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

                                throw new \Filament\Support\Exceptions\Halt;
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
                                                Text::make(fn () => ($this->parsedData['total_rows'] ?? 0).' Baris')
                                                    ->size(TextSize::Large)
                                                    ->weight(FontWeight::Bold)
                                                    ->color('gray'),
                                            ])->columnSpan(1),

                                        Section::make('Siap Diimpor')
                                            ->icon('heroicon-o-check-circle')
                                            ->schema([
                                                Text::make(fn () => ($this->parsedData['valid_count'] ?? 0).' Data Valid')
                                                    ->size(TextSize::Large)
                                                    ->weight(FontWeight::Bold)
                                                    ->color('success'),
                                            ])->columnSpan(1),

                                        Section::make('Perlu Perbaikan')
                                            ->icon('heroicon-o-x-circle')
                                            ->schema([
                                                Text::make(fn () => ($this->parsedData['invalid_count'] ?? 0).' Data Bermasalah')
                                                    ->size(TextSize::Large)
                                                    ->weight(FontWeight::Bold)
                                                    ->color('danger'),
                                            ])->columnSpan(1),
                                    ]),
                                ]),

                            // Section 4: Tindakan Data Bermasalah
                            Section::make('Tindakan Data Bermasalah')
                                ->description(fn () => 'Ditemukan '.($this->parsedData['invalid_count'] ?? 0).' baris data yang belum memenuhi kriteria validasi. Baris ini dilewati pada sesi saat ini dan dapat diperbaiki di Excel.')
                                ->icon('heroicon-m-exclamation-triangle')
                                ->iconColor('danger')
                                ->visible(fn () => ! empty($this->parsedData) && ($this->parsedData['invalid_count'] ?? 0) > 0)
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

                                                $fileName = 'Data_Bermasalah_Import_Anggota_'.now()->format('Ymd_His').'.csv';

                                                return Excel::download(
                                                    new MemberImportErrorsExport($invalidRows),
                                                    $fileName
                                                );
                                            }),
                                    ])->alignLeft(),
                                ]),

                            // Section: Filter Baris Pratinjau
                            ToggleButtons::make('filter_tab')
                                ->label('Filter Tampilan Pratinjau Data')
                                ->options(fn () => [
                                    'all' => 'Semua Data ('.($this->parsedData['total_rows'] ?? 0).')',
                                    'valid' => 'Siap Diimpor ('.($this->parsedData['valid_count'] ?? 0).')',
                                    'invalid' => 'Perlu Perbaikan ('.($this->parsedData['invalid_count'] ?? 0).')',
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

                                throw new \Filament\Support\Exceptions\Halt;
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
                                ->description('Rincian data yang siap dimasukkan ke dalam database.')
                                ->icon('heroicon-m-clipboard-document-check')
                                ->schema([
                                    Grid::make(['default' => 1, 'sm' => 2])->schema([
                                        Section::make('Data Siap Diproses')
                                            ->icon('heroicon-o-check-circle')
                                            ->schema([
                                                Text::make(fn () => ($this->parsedData['valid_count'] ?? 0).' Anggota Siap Disimpan')
                                                    ->size(TextSize::Large)
                                                    ->weight(FontWeight::Bold)
                                                    ->color('success'),
                                                Text::make('Data akan disimpan dan QR Code dihasilkan otomatis.')
                                                    ->size(TextSize::Small)
                                                    ->color('gray'),
                                            ])->columnSpan(1),

                                        Section::make('Data Dilewati')
                                            ->icon('heroicon-o-x-circle')
                                            ->schema([
                                                Text::make(fn () => ($this->parsedData['invalid_count'] ?? 0).' Baris Dilewati')
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
                                ->description('Sesuaikan pengaturan otomatisasi untuk data yang akan diimpor.')
                                ->icon('heroicon-m-cog-6-tooth')
                                ->schema([
                                    Grid::make(['default' => 1, 'md' => 4])->schema([
                                        Checkbox::make('auto_generate_kta')
                                            ->label('Otomatis Buat KTA')
                                            ->helperText('Gunakan template KTA resmi.')
                                            ->default(true),
                                        Checkbox::make('is_permanent')
                                            ->label('Status Disetujui')
                                            ->helperText('Tetapkan status disetujui.')
                                            ->default(true),
                                        Checkbox::make('status')
                                            ->label('Kehadiran Wajib')
                                            ->helperText('Tetapkan target aktif.')
                                            ->default(true),
                                        Checkbox::make('allow_update')
                                            ->label('Perbarui Jika Ada')
                                            ->helperText('Perbarui data jika ID sudah terdaftar.')
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
                $filePath = storage_path('app/public/'.$state);
            } elseif (is_object($state) && method_exists($state, 'getRealPath')) {
                $filePath = $state->getRealPath();
            }

            if (! $filePath || ! file_exists($filePath)) {
                $filePath = Storage::disk('public')->path($state);
            }

            if (! file_exists($filePath)) {
                throw new \Exception("Berkas spreadsheet tidak ditemukan di penyimpanan: {$filePath}");
            }

            $parser = new MemberImportParser(auth()->user());
            $this->parsedData = $parser->parse($filePath);

            // Trik UX Otomatis: jika ada error, buka langsung tab 'invalid'
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
                    ->body('Berkas spreadsheet tidak memuat baris data anggota untuk diproses.')
                    ->info()
                    ->send();
            } elseif ($invalidCount > 0) {
                Notification::make()
                    ->title('Pemeriksaan Selesai')
                    ->body("Ditemukan {$validCount} data siap diimpor dan {$invalidCount} data bermasalah. Silakan periksa rinciannya pada tabel di bawah.")
                    ->warning()
                    ->send();
            } else {
                Notification::make()
                    ->title('Pemeriksaan Selesai')
                    ->body("Semua {$validCount} data anggota valid dan siap untuk disimpan.")
                    ->success()
                    ->send();
            }

            $this->resetTable();
        } catch (\Throwable $e) {
            $this->parsedData = [];
            Notification::make()
                ->title('Gagal Membaca File')
                ->body('Terjadi kesalahan saat memproses berkas: '.$e->getMessage())
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
     * Konfigurasi tabel pratinjau (Murni Read-Only, tanpa input form)
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
                        $fileName = 'Data_Bermasalah_Import_Anggota_'.now()->format('Ymd_His').'.csv';

                        return Excel::download(
                            new MemberImportErrorsExport($invalidRows),
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

                TextColumn::make('id_status')
                    ->label('Status ID')
                    ->badge()
                    ->state(fn ($record) => $record['id_status_label'] ?? '-')
                    ->color(fn ($record) => match ($record['id_status'] ?? '') {
                        'auto_id' => 'info',
                        'update' => 'warning',
                        'manual_id' => 'success',
                        'no_code' => 'gray',
                        'broken_chain' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('member_code')
                    ->label('ID Anggota')
                    ->badge()
                    ->state(function ($record) {
                        if (! empty($record['member_code'])) {
                            return ($record['is_update'] ?? false)
                                ? "{$record['member_code']} (Update)"
                                : $record['member_code'];
                        }
                        if (($record['id_status'] ?? '') === 'auto_id') {
                            $prefix = $record['calculated_prefix'] ?? '';

                            return $prefix ? "Otomatis ({$prefix}XXX)" : 'Otomatis';
                        }
                        if (($record['id_status'] ?? '') === 'no_code') {
                            return 'Tanpa ID';
                        }

                        return '-';
                    })
                    ->color(fn ($record) => match ($record['id_status'] ?? '') {
                        'auto_id' => 'info',
                        'update' => 'warning',
                        'manual_id' => 'success',
                        'no_code' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('full_name')
                    ->label('Nama Lengkap')
                    ->searchable(),

                TextColumn::make('group_name')
                    ->label('Kelompok')
                    ->badge()
                    ->color(fn ($record) => ! empty($record['group_id']) ? 'info' : 'danger')
                    ->placeholder('-'),

                TextColumn::make('gender_label')
                    ->label('L/P')
                    ->badge()
                    ->color(fn ($state) => match (strtoupper((string) $state)) {
                        'L' => 'info',
                        'P' => 'danger',
                        default => 'gray',
                    })
                    ->alignCenter(),

                TextColumn::make('birth_date')
                    ->label('Tgl Lahir / Usia')
                    ->formatStateUsing(function ($state, $record) {
                        if (! $state) {
                            return '-';
                        }
                        $formatted = Carbon::parse($state)->format('d/m/Y');
                        if (isset($record['age']) && $record['age'] !== null) {
                            $formatted .= " ({$record['age']} th)";
                        }

                        return $formatted;
                    })
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

        $allowUpdate = $this->data['allow_update'] ?? true;
        $isPermanent = $this->data['is_permanent'] ?? true;
        $status = $this->data['status'] ?? true;
        $autoGenerateKta = $this->data['auto_generate_kta'] ?? true;

        try {
            DB::beginTransaction();

            $insertedCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $createdMembers = [];

            $user = auth()->user();
            $isSuperAdmin = $user ? $user->isSuperAdmin() : true;
            $manageableIds = $user ? $user->getManageableGroupIds() : [];

            foreach ($validRows as $row) {
                if (! $isSuperAdmin && ! in_array($row['group_id'], $manageableIds)) {
                    throw new \Exception("Kelompok ID {$row['group_id']} berada di luar wilayah wewenang Anda.");
                }

                $memberData = [
                    'full_name' => $row['full_name'],
                    'nick_name' => $row['nick_name'],
                    'group_id' => $row['group_id'],
                    'birth_date' => $row['birth_date'],
                    'age' => $row['age'],
                    'age_group_id' => $row['age_group_id'],
                    'gender' => $row['gender'],
                    'status' => $status,
                    'is_permanent' => $isPermanent,
                    'membership_type' => $row['membership_type'] ?? \App\Enums\MembershipType::ANGGOTA->value,
                ];

                // Mode Update jika ID Anggota sudah terdaftar aktif
                if (! empty($row['member_code']) && ($row['is_update'] ?? false)) {
                    if (! $allowUpdate) {
                        $skippedCount++;

                        continue;
                    }
                    $existingMember = Member::where('member_code', $row['member_code'])->first();
                    if ($existingMember) {
                        $existingMember->update($memberData);
                        $updatedCount++;

                        continue;
                    }
                }

                // Tambah Anggota Baru (member_code null akan di-generate otomatis oleh MemberObserver jika kelompok punya kode)
                $memberData['member_code'] = ! empty($row['member_code']) ? $row['member_code'] : null;
                $newMember = new Member($memberData);
                $newMember->save(); // Memicu MemberObserver untuk auto-ID & auto-QR
                $createdMembers[] = $newMember;
                $insertedCount++;
            }

            DB::commit();

            // Bersihkan file spreadsheet sementara yang diunggah
            $file = $this->data['file'] ?? null;
            if ($file && is_string($file)) {
                Storage::disk('public')->delete($file);
            }

            // Bersihkan cache terkait master data
            $userId = auth()->id();
            if ($userId) {
                Cache::forget("member_import_group_map_u{$userId}");
            }
            Cache::forget('member_import_group_map_public');
            Cache::forget('member_import_group_map');
            Cache::forget('import_group_cache');
            Cache::forget('import_group_cache_validator');

            $summary = [];
            if ($insertedCount > 0) {
                $summary[] = "{$insertedCount} anggota baru ditambahkan";
            }
            if ($updatedCount > 0) {
                $summary[] = "{$updatedCount} anggota diperbarui";
            }
            if ($skippedCount > 0) {
                $summary[] = "{$skippedCount} anggota dilewati (opsi perbarui nonaktif)";
            }

            // Pembuatan KTA Otomatis (Anti-timeout)
            $ktaCreatedCount = 0;
            if ($autoGenerateKta && ! empty($createdMembers)) {
                if (count($createdMembers) <= 30) {
                    $cardService = new \App\Services\CardGeneratorService;
                    $hierarchyService = app(\App\Services\Groups\GroupHierarchyService::class);

                    foreach ($createdMembers as $member) {
                        if ($member->group) {
                            $resTmpl = $hierarchyService->getEffectiveCardTemplate($member->group);
                            if ($resTmpl['template']) {
                                try {
                                    $cardService->generate($resTmpl['template'], $member);
                                    $ktaCreatedCount++;
                                } catch (\Throwable $e) {
                                    \Illuminate\Support\Facades\Log::warning("Gagal generate KTA anggota #{$member->id}: ".$e->getMessage());
                                }
                            }
                        }
                    }
                }
            }

            $bodyMsg = implode(', ', $summary).'. Seluruh QR Code anggota telah dihasilkan secara otomatis.';
            if ($ktaCreatedCount > 0) {
                $bodyMsg .= " Sebanyak {$ktaCreatedCount} KTA berhasil dibuat.";
            } elseif ($autoGenerateKta && count($createdMembers) > 30) {
                $bodyMsg .= ' Karena data cukup banyak ('.count($createdMembers)." anggota baru), KTA dapat dibuat secara massal via tombol 'Buat KTA Terpilih' di daftar anggota.";
            }

            Notification::make()
                ->title('Impor Anggota Berhasil')
                ->body($bodyMsg)
                ->success()
                ->send();

            $this->redirect(MemberResource::getUrl('index'));
        } catch (\Throwable $e) {
            DB::rollBack();

            Notification::make()
                ->title('Gagal Menyimpan Data')
                ->body('Terjadi kesalahan database saat menyimpan: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }
}
