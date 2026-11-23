<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    @livewire(\App\Filament\Widgets\EarlyScansWidget::class, ['filters' => $filters], key('scans-early-' . md5(json_encode($filters))))
    @livewire(\App\Filament\Widgets\RecentScansWidget::class, ['filters' => $filters], key('scans-recent-' . md5(json_encode($filters))))
</div>
