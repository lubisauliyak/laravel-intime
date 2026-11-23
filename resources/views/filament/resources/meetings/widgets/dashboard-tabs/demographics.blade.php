<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    @livewire(\App\Filament\Widgets\TargetPopulationWidget::class, ['filters' => $filters], key('demo-pop-age-' . md5(json_encode($filters))))
    @livewire(\App\Filament\Widgets\AgeGroupParticipationWidget::class, ['filters' => $filters], key('demo-act-age-' . md5(json_encode($filters))))
    @livewire(\App\Filament\Widgets\TargetGenderDistributionWidget::class, ['filters' => $filters], key('demo-pop-gen-' . md5(json_encode($filters))))
    @livewire(\App\Filament\Widgets\GenderActivityWidget::class, ['filters' => $filters], key('demo-act-gen-' . md5(json_encode($filters))))
</div>
