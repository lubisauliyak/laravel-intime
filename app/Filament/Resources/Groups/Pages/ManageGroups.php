<?php

namespace App\Filament\Resources\Groups\Pages;

use App\Exports\GroupsRawExport;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\Groups\Widgets\GroupStatsWidget;
use App\Models\Group;
use App\Models\Level;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ManageGroups extends ManageRecords
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Ekspor Data Grup')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->hiddenLabel()
                ->outlined()
                ->tooltip('Ekspor Data Grup (Excel)')
                ->visible(fn () => auth()->user()?->can('export', Group::class) ?? false)
                ->modalHeading('Ekspor Data Grup')
                ->modalDescription('Pilih cakupan data grup yang ingin diekspor ke dalam file spreadsheet Excel (.xlsx).')
                ->modalIcon('heroicon-o-document-arrow-down')
                ->modalSubmitActionLabel('Unduh File Excel')
                ->form([
                    Radio::make('export_scope')
                        ->label('Cakupan Data')
                        ->options([
                            'filtered' => 'Sesuai Filter, Pencarian & Tab Saat Ini',
                            'all_manageable' => 'Seluruh Grup di Wilayah Wewenang Saya',
                        ])
                        ->descriptions([
                            'filtered' => 'Hanya mengekspor baris data grup yang sedang aktif ditampilkan di tabel saat ini.',
                            'all_manageable' => 'Mengekspor seluruh grup di wilayah wewenang Anda tanpa terikat filter, pencarian, atau tab tabel saat ini.',
                        ])
                        ->default('filtered')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $scope = $data['export_scope'] ?? 'filtered';

                    if ($scope === 'all_manageable') {
                        $user = auth()->user();
                        $query = Group::query();
                        if ($user && !$user->isSuperAdmin()) {
                            $query->whereIn('id', $user->getManageableGroupIds());
                        }
                        $groupIds = $query->pluck('id')->toArray();
                    } else {
                        // Ambil baris grup sesuai filter, search, & tab yang sedang aktif
                        $groupIds = $this->getFilteredTableQuery()->pluck('groups.id')->toArray();
                    }

                    if (empty($groupIds)) {
                        Notification::make()
                            ->warning()
                            ->title('Tidak Ada Data')
                            ->body('Tidak ditemukan data grup yang dapat diekspor sesuai cakupan yang dipilih.')
                            ->send();
                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('Ekspor Dimulai')
                        ->body('File data grup sedang dipersiapkan dan akan segera diunduh.')
                        ->send();

                    $filename = 'Data_Grup_' . now()->format('Ymd_His') . '.xlsx';

                    return Excel::download(new GroupsRawExport($groupIds), $filename);
                }),

            Action::make('import')
                ->label('Impor Data')
                ->icon('heroicon-m-arrow-up-tray')
                ->hiddenLabel()
                ->outlined()
                ->color('info')
                ->tooltip('Impor Data Grup')
                ->visible(fn () => auth()->user()?->can('import', Group::class) ?? false)
                ->url(fn (): string => GroupResource::getUrl('import')),

            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            GroupStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        $stats = GroupResource::getGroupStats();
        $levelCounts = $stats['levels'] ?? [];

        $tabs = [
            'all' => Tab::make('Semua')
                ->badge($stats['total'] ?? 0),
        ];

        // Ambil levels terurut berdasarkan level_number desc (hierarki tertinggi ke terendah)
        $levels = Level::orderBy('level_number', 'desc')->get();

        foreach ($levels as $level) {
            $count = $levelCounts[$level->id] ?? 0;
            // Tampilkan tab level jika ada grup pada level ini dalam jangkauan user
            if ($count > 0) {
                $tabs['level_' . $level->id] = Tab::make($level->display_name)
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('groups.level_id', $level->id))
                    ->badge($count)
                    ->badgeColor($level->getColor());
            }
        }

        if (($stats['trashed'] ?? 0) > 0) {
            $tabs['trashed'] = Tab::make('Sampah')
                ->modifyQueryUsing(fn (Builder $query) => $query->onlyTrashed())
                ->badge($stats['trashed'])
                ->badgeColor('danger');
        }

        return $tabs;
    }
}
