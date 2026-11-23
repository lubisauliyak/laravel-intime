@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();
    $activeFilter = $this->filter ?? 'month';
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section
        :description="$description"
        :heading="$heading"
        :collapsible="$isCollapsible"
    >
        <x-slot name="afterHeader">
            <x-filament::button.group class="fi-wi-chart-filter-group">
                <x-filament::button
                    size="xs"
                    :color="$activeFilter === 'all' ? 'primary' : 'gray'"
                    :outlined="$activeFilter !== 'all'"
                    wire:click="setFilter('all')"
                    tooltip="Semua Riwayat"
                >
                    ALL
                </x-filament::button>

                <x-filament::button
                    size="xs"
                    :color="$activeFilter === 'year' ? 'primary' : 'gray'"
                    :outlined="$activeFilter !== 'year'"
                    wire:click="setFilter('year')"
                    tooltip="Tahun Ini"
                >
                    Y
                </x-filament::button>

                <x-filament::button
                    size="xs"
                    :color="$activeFilter === 'month' ? 'primary' : 'gray'"
                    :outlined="$activeFilter !== 'month'"
                    wire:click="setFilter('month')"
                    tooltip="Bulan Ini"
                >
                    M
                </x-filament::button>
            </x-filament::button.group>
        </x-slot>

        <div
            @if ($pollingInterval = $this->getPollingInterval())
                wire:poll.{{ $pollingInterval }}="updateChartData"
            @endif
        >
            <div
                x-load
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                wire:ignore
                data-chart-type="{{ $type }}"
                x-data="chart({
                            cachedData: @js($this->getCachedData()),
                            options: @js($this->getOptions()),
                            type: @js($type),
                        })"
                {{
                    (new ComponentAttributeBag)
                        ->color(ChartWidgetComponent::class, $color)
                        ->class([
                            'fi-wi-chart-canvas-ctn',
                            'fi-wi-chart-canvas-ctn-no-aspect-ratio' => filled($maxHeight = $this->getMaxHeight()),
                        ])
                        ->style([
                            'max-height: ' . $maxHeight => filled($maxHeight),
                        ])
                }}
            >
                <canvas x-ref="canvas"></canvas>

                <span
                    x-ref="backgroundColorElement"
                    class="fi-wi-chart-bg-color"
                ></span>

                <span
                    x-ref="borderColorElement"
                    class="fi-wi-chart-border-color"
                ></span>

                <span
                    x-ref="gridColorElement"
                    class="fi-wi-chart-grid-color"
                ></span>

                <span
                    x-ref="textColorElement"
                    class="fi-wi-chart-text-color"
                ></span>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
