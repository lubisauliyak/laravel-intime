<?php

namespace App\Filament\Resources\Meetings\Widgets;

use App\Models\Group;
use App\Models\Level;
use App\Models\Meeting;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

class MeetingFilterWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.resources.meetings.widgets.meeting-filter-widget';

    protected int|string|array $columnSpan = 'full';

    public ?Meeting $record = null;

    public $filters = [
        'level_id' => null,
        'group_id' => null,
    ];

    public function mount(): void
    {
        $this->form->fill($this->filters);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Filter Statistik')
                    ->description('Filter data statistik di bawah berdasarkan level atau grup tertentu')
                    ->schema([
                        Select::make('level_id')
                            ->label('Level Grup')
                            ->options(Level::pluck('name', 'id'))
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('group_id', null))
                            ->placeholder('Semua Level'),
                        Select::make('group_id')
                            ->label('Nama Grup')
                            ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                $levelId = $get('level_id');
                                $user = auth()->user();

                                $query = Group::query();

                                if (! $user->isSuperAdmin() && $user->group_id) {
                                    $query->whereIn('groups.id', $user->group->getAllDescendantIds());
                                }

                                if ($levelId) {
                                    $query->where('level_id', $levelId);
                                }

                                return $query->with('level')
                                    ->join('levels', 'levels.id', '=', 'groups.level_id')
                                    ->orderBy('levels.level_number', 'asc')
                                    ->orderBy('groups.name', 'asc')
                                    ->select('groups.*')
                                    ->get()
                                    ->mapWithKeys(fn ($g) => [
                                        $g->id => "({$g->level?->code}) {$g->name}",
                                    ]);
                            })
                            ->live()
                            ->searchable()
                            ->getOptionLabelUsing(fn ($value): ?string => Group::with('level')->find($value)?->full_name)
                            ->placeholder('Semua Grup'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ])
            ->statePath('filters');
    }

    public function updatedFilters(): void
    {
        $this->dispatch('filtersUpdated', filters: $this->filters);
    }
}
