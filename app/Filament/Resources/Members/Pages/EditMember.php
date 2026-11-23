<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Models\Group;
use App\Services\Groups\GroupHierarchyService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Set;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    public bool $memberCodeLocked = true;

    public ?string $initialMemberCode = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->initialMemberCode = $this->record->member_code;
    }

    public function isMemberCodeLocked(): bool
    {
        return $this->memberCodeLocked;
    }

    public function lockMemberCode(): void
    {
        $this->memberCodeLocked = true;
    }

    public function unlockMemberCode(): void
    {
        $this->memberCodeLocked = false;
    }

    public function revertMemberCode(?Set $set = null): void
    {
        $this->memberCodeLocked = true;
        if ($set) {
            $set('member_code', $this->initialMemberCode);
        }
        $this->data['member_code'] = $this->initialMemberCode;
    }

    public function autoFillMemberCode(?int $groupId, ?Set $set = null): ?string
    {
        if (! $groupId) {
            if ($set) {
                $set('member_code', null);
            }
            $this->data['member_code'] = null;

            return null;
        }

        $group = Group::find($groupId);
        if (! $group) {
            return null;
        }

        $hierarchyService = app(GroupHierarchyService::class);
        $validation = $hierarchyService->validateChainIntegrity($group);

        if ($validation['status'] === 'no_code' || ! $validation['is_valid']) {
            return null;
        }

        try {
            $nextCode = $hierarchyService->generateNextMemberCode($group);
            if ($set) {
                $set('member_code', $nextCode);
            }
            $this->data['member_code'] = $nextCode;
            $this->memberCodeLocked = true;

            return $nextCode;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Safety guard: Jika awalnya punya ID tapi disubmit kosong tanpa sengaja, kembalikan ke ID awal
        if (! empty($this->initialMemberCode) && empty($data['member_code'])) {
            $data['member_code'] = $this->initialMemberCode;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
