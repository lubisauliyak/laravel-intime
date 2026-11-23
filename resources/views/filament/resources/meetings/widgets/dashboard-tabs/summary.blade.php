<div class="space-y-6">
    <div class="grid grid-cols-1 gap-6">
        @livewire(\App\Filament\Widgets\AttendanceOverview::class, ['filters' => $filters], key('summary-overview-' . md5(json_encode($filters))))
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @livewire(\App\Filament\Widgets\TopInsightWidget::class, ['filters' => $filters], key('summary-insight-' . md5(json_encode($filters))))
        @livewire(\App\Filament\Widgets\ScanningPeakTimeWidget::class, ['filters' => $filters], key('summary-peak-' . md5(json_encode($filters))))
    </div>
</div>
