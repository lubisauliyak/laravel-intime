<?php

namespace App\Filament\Resources\Meetings\Pages;

use App\Filament\Resources\Meetings\MeetingResource;
use App\Filament\Resources\Meetings\Widgets\MeetingStatsWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMeetings extends ListRecords
{
    protected static string $resource = MeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MeetingStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        $stats = MeetingResource::getMeetingStats();
        $user = auth()->user();

        $today = now()->toDateString();
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        $tabs = [
            'all' => Tab::make('Semua')
                ->badge($stats['total'] ?? 0),

            'upcoming' => Tab::make('Mendatang')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('meeting_date', '>=', $today))
                ->badge($stats['upcoming'] ?? 0)
                ->badgeColor('success'),

            'this_month' => Tab::make('Dalam Sebulan')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereBetween('meeting_date', [$startOfMonth, $endOfMonth]))
                ->badge($stats['month'] ?? 0)
                ->badgeColor('info'),
        ];

        if ($user && !$user->isSuperAdmin() && $user->group_id) {
            $tabs['my_group'] = Tab::make('Grup Saya')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('group_id', $user->group_id))
                ->badge($stats['my_group'] ?? 0)
                ->badgeColor('warning');
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
