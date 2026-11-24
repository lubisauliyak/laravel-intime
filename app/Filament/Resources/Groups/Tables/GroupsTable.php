<?php

namespace App\Filament\Resources\Groups\Tables;

use App\Exports\GroupsRawExport;
use App\Models\Group;
use App\Models\Level;
use App\Models\Member;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;

class GroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('level.name')
                    ->label('Tingkat')
                    ->badge()
                    ->color(fn (Group $record): string => $record->level?->getColor() ?? 'gray')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama Grup')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('-')
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('calculated_prefix')
                    ->label('Prefix ID')
                    ->alignCenter()
                    ->state(fn ($record) => app(\App\Services\Groups\GroupHierarchyService::class)->getCalculatedPrefix($record) ?? '-')
                    ->badge()
                    ->color(fn ($state): string => $state !== '-' ? 'success' : 'gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('parent.full_name')
                    ->label('Induk Grup')
                    ->searchable(['name'])
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('effective_template')
                    ->label('Template KTA')
                    ->state(function ($record) {
                        $res = app(\App\Services\Groups\GroupHierarchyService::class)->getEffectiveCardTemplate($record);
                        if (! $res['template']) {
                            return '-';
                        }
                        $name = $res['template']->name;

                        return $res['is_locked']
                            ? "🔒 {$name}"
                            : $name;
                    })
                    ->tooltip(function ($record) {
                        $res = app(\App\Services\Groups\GroupHierarchyService::class)->getEffectiveCardTemplate($record);
                        if (! $res['template'] || ! $res['is_locked']) {
                            return null;
                        }
                        $parentName = $res['inherited_from']?->name ?? 'Induk';

                        return "Template dikunci oleh {$parentName}";
                    })
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('members_count')
                    ->label('Anggota')
                    ->state(function (Group $record): int {
                        $countsMap = Cache::remember('group_direct_member_counts_map', 60, function () {
                            return Member::groupBy('group_id')
                                ->selectRaw('group_id, count(*) as total')
                                ->pluck('total', 'group_id')
                                ->toArray();
                        });

                        $total = 0;
                        foreach ($record->getAllDescendantIds() as $id) {
                            $total += ($countsMap[$id] ?? 0);
                        }

                        return $total;
                    })
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->toggleable(),
                IconColumn::make('status')
                    ->label('Aktif')
                    ->alignCenter()
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('level_id')
                    ->label('Tingkat')
                    ->relationship('level', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                SelectFilter::make('parent_id')
                    ->label('Induk Grup')
                    ->relationship('parent', 'groups.name', fn ($query) => $query->whereHas('children'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('status')
                    ->label('Status Aktif')
                    ->placeholder('Semua Status')
                    ->trueLabel('Aktif')
                    ->falseLabel('Kurang Aktif')
                    ->queries(
                        true: fn ($query) => $query->where('status', true),
                        false: fn ($query) => $query->where('status', false),
                    ),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Lihat'),
                    EditAction::make()
                        ->label('Ubah')
                        ->visible(fn ($record) => $record->canBeManagedBy(auth()->user())),
                    DeleteAction::make()
                        ->label('Hapus')
                        ->visible(fn ($record) => $record->canBeManagedBy(auth()->user())),
                ]),
            ])
            ->modifyQueryUsing(
                fn ($query) => $query
                    ->addSelect([
                        'level_sort' => Level::select('level_number')
                            ->whereColumn('levels.id', 'groups.level_id')
                            ->limit(1),
                    ])
                    ->orderBy('level_sort', 'desc')
                    ->orderBy('name', 'asc')
            )
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('export_selected')
                        ->label('Ekspor Terpilih')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->visible(fn () => auth()->user()?->can('export', Group::class) ?? false)
                        ->action(function (Collection $records) {
                            $allowedRecords = $records->filter(fn (Group $record) => auth()->user()->can('export', $record));

                            if ($allowedRecords->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Tidak Ada Data yang Diizinkan')
                                    ->body('Seluruh grup yang dipilih berada di luar wilayah wewenang Anda.')
                                    ->send();

                                return;
                            }

                            if ($allowedRecords->count() < $records->count()) {
                                $skipped = $records->count() - $allowedRecords->count();
                                Notification::make()
                                    ->info()
                                    ->title('Sebagian Data Dilewati')
                                    ->body("{$skipped} grup dilewati karena di luar wilayah wewenang Anda.")
                                    ->send();
                            }

                            $filename = 'Data_Grup_Terpilih_'.now()->format('Ymd_His').'.xlsx';

                            return Excel::download(new GroupsRawExport($allowedRecords->pluck('id')->toArray()), $filename);
                        }),
                    DeleteBulkAction::make()
                        ->label('Hapus Terpilih')
                        ->action(function (Collection $records, DeleteBulkAction $action) {
                            $allowedRecords = $records->filter(fn (Group $record) => auth()->user()->can('delete', $record));
                            $allowedRecords->each(fn (Group $record) => $record->delete());

                            if ($allowedRecords->count() < $records->count()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Sebagian Ditolak')
                                    ->body('Beberapa grup gagal dihapus karena bukan bagian dari wilayah wewenang Anda atau merupakan grup Anda sendiri.')
                                    ->send();
                            } else {
                                $action->success();
                            }
                        }),
                    RestoreBulkAction::make()
                        ->label('Pulihkan Terpilih')
                        ->action(function (Collection $records, RestoreBulkAction $action) {
                            $allowedRecords = $records->filter(fn (Group $record) => auth()->user()->can('restore', $record));
                            $allowedRecords->each(fn (Group $record) => $record->restore());

                            if ($allowedRecords->count() < $records->count()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Sebagian Ditolak')
                                    ->body('Beberapa grup gagal dipulihkan karena bukan bagian dari wilayah wewenang Anda.')
                                    ->send();
                            } else {
                                $action->success();
                            }
                        }),
                    ForceDeleteBulkAction::make()
                        ->label('Hapus Permanen Terpilih')
                        ->action(function (Collection $records, ForceDeleteBulkAction $action) {
                            $allowedRecords = $records->filter(fn (Group $record) => auth()->user()->can('forceDelete', $record));
                            $allowedRecords->each(fn (Group $record) => $record->forceDelete());

                            if ($allowedRecords->count() < $records->count()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Sebagian Ditolak')
                                    ->body('Beberapa grup gagal dihapus permanen karena bukan bagian dari wilayah wewenang Anda atau merupakan grup Anda sendiri.')
                                    ->send();
                            } else {
                                $action->success();
                            }
                        }),
                ])->label('Aksi Massal'),
            ]);
    }
}
