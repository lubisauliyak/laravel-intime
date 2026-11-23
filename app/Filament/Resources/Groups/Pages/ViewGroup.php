<?php

namespace App\Filament\Resources\Groups\Pages;

use App\Filament\Resources\Groups\GroupResource;
use App\Models\Group;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Carbon;

class ViewGroup extends ViewRecord
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshMemberAge')
                ->label('Refresh Usia Anggota')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->hiddenLabel()
                ->outlined()
                ->tooltip('Hitung ulang & mutakhirkan usia seluruh anggota di grup ini (dan sub-grup) berdasarkan tanggal lahir')
                ->visible(fn (Group $record) => auth()->user()->can('refreshMemberAge', $record))
                ->requiresConfirmation()
                ->modalHeading('Perbarui Usia Anggota Grup')
                ->modalIcon('heroicon-o-arrow-path')
                ->modalDescription('Apakah Anda yakin ingin menghitung ulang usia seluruh anggota di dalam grup ini beserta seluruh sub-grup di bawahnya? Usia akan dihitung ulang secara otomatis berdasarkan tanggal lahir masing-masing anggota. Kategori usia (age group) tidak akan diubah.')
                ->modalSubmitActionLabel('Ya, Perbarui Usia')
                ->action(function (Group $record) {
                    $groupIds = $record->getAllDescendantIds();

                    $updatedCount = 0;
                    $totalEligible = 0;

                    Member::whereIn('group_id', $groupIds)
                        ->whereNotNull('birth_date')
                        ->chunkById(250, function ($members) use (&$updatedCount, &$totalEligible) {
                            foreach ($members as $member) {
                                $totalEligible++;
                                $calculatedAge = Carbon::parse($member->birth_date)->age;

                                if ($member->age !== $calculatedAge) {
                                    $member->updateQuietly(['age' => $calculatedAge]);
                                    $updatedCount++;
                                }
                            }
                        });

                    if ($totalEligible === 0) {
                        Notification::make()
                            ->warning()
                            ->title('Tidak Ada Data Usia')
                            ->body('Tidak ditemukan anggota dengan data tanggal lahir yang valid di grup ini.')
                            ->send();

                        return;
                    }

                    if ($updatedCount > 0) {
                        Notification::make()
                            ->success()
                            ->title('Pembaruan Usia Selesai')
                            ->body("Berhasil memutakhirkan usia untuk {$updatedCount} anggota (dari total {$totalEligible} anggota dengan tanggal lahir).")
                            ->send();
                    } else {
                        Notification::make()
                            ->info()
                            ->title('Data Usia Sudah Mutakhir')
                            ->body('Seluruh anggota di grup ini sudah memiliki nilai usia yang sesuai dengan tanggal lahir.')
                            ->send();
                    }
                }),
            EditAction::make()
                ->visible(fn ($record) => $record->canBeManagedBy(auth()->user())),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return 'Informasi Umum';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-information-circle';
    }

    public function getTitle(): string
    {
        return 'Informasi Umum';
    }
}
