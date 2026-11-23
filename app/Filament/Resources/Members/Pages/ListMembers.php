<?php

namespace App\Filament\Resources\Members\Pages;

use App\Exports\MembersRawExport;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Widgets\MemberStatsWidget;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Ekspor Data Anggota')
                ->icon('heroicon-o-arrow-down-tray')
                ->hiddenLabel()
                ->outlined()
                ->color('success')
                ->tooltip('Ekspor Data Anggota (Excel)')
                ->visible(fn () => auth()->user()?->can('export', Member::class) ?? false)
                ->modalHeading('Ekspor Data Anggota')
                ->modalDescription('Pilih cakupan data anggota yang ingin diekspor ke dalam file spreadsheet Excel (.xlsx).')
                ->modalIcon('heroicon-o-arrow-down-tray')
                ->modalSubmitActionLabel('Unduh File Excel')
                ->form([
                    Radio::make('export_scope')
                        ->label('Cakupan Data')
                        ->options([
                            'filtered' => 'Sesuai Filter, Pencarian & Tab Saat Ini',
                            'all_manageable' => 'Seluruh Anggota Aktif di Wilayah Saya',
                        ])
                        ->descriptions([
                            'filtered' => 'Hanya mengekspor baris data yang sedang aktif ditampilkan di tabel saat ini.',
                            'all_manageable' => 'Mengekspor seluruh anggota aktif di wilayah wewenang Anda tanpa terikat filter atau pencarian tabel saat ini.',
                        ])
                        ->default('filtered')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $scope = $data['export_scope'] ?? 'filtered';

                    if ($scope === 'all_manageable') {
                        $user = auth()->user();
                        $query = Member::query()->where('status', true);
                        if ($user && !$user->isSuperAdmin()) {
                            $query->whereIn('group_id', $user->getManageableGroupIds());
                        }
                        $memberIds = $query->pluck('id')->toArray();
                    } else {
                        $memberIds = $this->getFilteredTableQuery()->pluck('members.id')->toArray();
                    }

                    if (empty($memberIds)) {
                        Notification::make()
                            ->warning()
                            ->title('Tidak Ada Data')
                            ->body('Tidak ditemukan data anggota yang dapat diekspor sesuai cakupan yang dipilih.')
                            ->send();
                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('Ekspor Dimulai')
                        ->body('File Excel sedang dipersiapkan dan akan segera diunduh.')
                        ->send();

                    $filename = 'Data-Anggota-' . now()->format('Ymd_His') . '.xlsx';

                    return (new MembersRawExport($memberIds))->download($filename);
                }),

            Action::make('import')
                ->label('Impor Data')
                ->icon('heroicon-m-arrow-up-tray')
                ->hiddenLabel()
                ->outlined()
                ->color('info')
                ->tooltip('Impor Data Anggota')
                ->visible(fn () => auth()->user()?->can('import', Member::class) ?? false)
                ->url(fn (): string => MemberResource::getUrl('import')),

            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MemberStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        $counts = MemberResource::getMemberCountsAndTrends();

        return [
            'all' => Tab::make('Semua')
                ->badge($counts['total']),
            'active' => Tab::make('Aktif')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('members.status', true)->where('members.is_permanent', true))
                ->badge($counts['active'])
                ->badgeColor('success'),
            'pending' => Tab::make('Menunggu Verifikasi')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('members.is_permanent', false))
                ->badge($counts['pending'])
                ->badgeColor($counts['pending'] > 0 ? 'warning' : 'gray'),
            'inactive' => Tab::make('Kurang Aktif')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('members.status', false))
                ->badge($counts['inactive'])
                ->badgeColor($counts['inactive'] > 0 ? 'danger' : 'gray'),
            'trashed' => Tab::make('Sampah')
                ->modifyQueryUsing(fn (Builder $query) => $query->onlyTrashed())
                ->badge($counts['trashed'])
                ->badgeColor($counts['trashed'] > 0 ? 'danger' : 'gray'),
        ];
    }
}
