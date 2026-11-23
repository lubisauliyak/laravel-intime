<?php

namespace App\Filament\Resources\Meetings\Pages;

use App\Filament\Resources\Meetings\MeetingResource;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use App\Filament\Widgets\AttendanceOverview;
use App\Filament\Widgets\TopInsightWidget;
use App\Filament\Widgets\TargetPopulationWidget;
use App\Filament\Widgets\AgeGroupParticipationWidget;
use App\Filament\Widgets\TargetGenderDistributionWidget;
use App\Filament\Widgets\GenderActivityWidget;
use App\Filament\Widgets\PunctualityStatsWidget;
use App\Filament\Widgets\PunctualityScansWidget;
use App\Filament\Widgets\ScanningPeakTimeWidget;
use App\Filament\Widgets\EarlyScansWidget;
use App\Filament\Widgets\RecentScansWidget;
use App\Filament\Widgets\GroupRanking;
use App\Filament\Widgets\AttendanceTrend;

class MeetingStats extends Page
{
    use InteractsWithRecord;

    protected static string $resource = MeetingResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;
        return $record
            ? auth()->user()->can('viewStats', $record)
            : auth()->user()->can('ViewStats:Meeting');
    }



    protected string $view = 'filament.resources.meetings.pages.meeting-stats';

    public $filters = [
        'level_id' => null,
        'group_id' => null,
        'meeting_id' => null,
    ];

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->filters['meeting_id'] = $this->record->id;
        
        if ($this->record->group) {
            $user = auth()->user();
            
            // Limit stats to the admin's own group if they are viewing a parent meeting
            if ($user && !$user->isSuperAdmin() && $user->group_id) {
                $ancestorIds = $user->group->getAllAncestorIds();
                if (in_array($this->record->group_id, $ancestorIds)) {
                    $this->filters['group_id'] = $user->group_id;
                    $this->filters['level_id'] = $user->group->level_id;
                    return;
                }
            }
            
            $this->filters['group_id'] = $this->record->group_id;
            $this->filters['level_id'] = $this->record->group->level_id;
        }
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-chart-pie';
    }

    public function getTitle(): string
    {
        return 'Statistik Pertemuan';
    }

    public static function getNavigationLabel(): string
    {
        return 'Statistik';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AttendanceTrend::class,
            AttendanceOverview::class,
            TargetPopulationWidget::class,
            AgeGroupParticipationWidget::class,
            TargetGenderDistributionWidget::class,
            GenderActivityWidget::class,
            PunctualityStatsWidget::class,
            PunctualityScansWidget::class,
            ScanningPeakTimeWidget::class,
            EarlyScansWidget::class,
            RecentScansWidget::class,
            GroupRanking::class,
            TopInsightWidget::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'record' => $this->record,
            'filters' => $this->filters,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 2;
    }

    public function getMaxContentWidth(): string
    {
        return 'full';
    }
}
