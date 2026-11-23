<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    @livewire(\App\Filament\Widgets\PunctualityStatsWidget::class, ['filters' => $filters], key('punc-stats-' . md5(json_encode($filters))))
    @livewire(\App\Filament\Widgets\PunctualityScansWidget::class, ['filters' => $filters], key('punc-scans-' . md5(json_encode($filters))))
</div>
