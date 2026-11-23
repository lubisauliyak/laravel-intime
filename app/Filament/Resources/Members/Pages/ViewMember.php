<?php

namespace App\Filament\Resources\Members\Pages;

use App\Models\Member;
use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    public static function getNavigationLabel(): string
    {
        return 'Informasi Anggota';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-information-circle';
    }

    public function getTitle(): string
    {
        return 'Informasi Anggota';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_card')
                ->label(fn (\App\Models\Member $record) => filled($record->membership_card_photo_path) ? 'Buat Ulang KTA' : 'Buat KTA')
                ->icon('heroicon-o-identification')
                ->color('info')
                ->visible(fn (\App\Models\Member $record) => auth()->user()->can('generateCard', $record))
                ->form([
                    \Filament\Forms\Components\Select::make('template_id')
                        ->label('Pilih Template')
                        ->options(\App\Models\CardTemplate::where('is_active', true)->pluck('name', 'id'))
                        ->default(function (\App\Models\Member $record) {
                            if ($record->group) {
                                $res = app(\App\Services\Groups\GroupHierarchyService::class)->getEffectiveCardTemplate($record->group);
                                if ($res['template']) {
                                    return $res['template']->id;
                                }
                            }
                            return null;
                        })
                        ->helperText(function (\App\Models\Member $record) {
                            if ($record->group) {
                                $res = app(\App\Services\Groups\GroupHierarchyService::class)->getEffectiveCardTemplate($record->group);
                                if ($res['template']) {
                                    $source = $res['is_locked'] ? "warisan dari {$res['inherited_from']?->name}" : "kelompok {$record->group->name}";
                                    return "Template default ({$source}): {$res['template']->name}. Anda dapat memilih template lain jika ingin mencetak khusus.";
                                }
                            }
                            return 'Kelompok anggota ini belum memiliki template KTA default. Silakan pilih template yang tersedia.';
                        })
                        ->required(),
                ])
                ->action(function (Member $record, array $data) {
                    $template = \App\Models\CardTemplate::find($data['template_id']);
                    if ($template) {
                        $service = new \App\Services\CardGeneratorService();
                        $service->generate($template, $record);
                        \Filament\Notifications\Notification::make()->title('KTA Berhasil Dibuat')->success()->send();
                        
                        return redirect(request()->header('Referer'));
                    }
                }),
            EditAction::make(),
        ];
    }
}
