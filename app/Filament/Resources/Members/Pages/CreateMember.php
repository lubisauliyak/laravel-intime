<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Models\Group;
use App\Services\Groups\GroupHierarchyService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    public bool $memberCodeLocked = true;

    public bool $memberCodeIsAuto = false;

    public function mount(): void
    {
        parent::mount();

        if (! empty($this->data['group_id'])) {
            $this->autoFillMemberCode((int) $this->data['group_id']);
        }
    }

    public function isMemberCodeLocked(): bool
    {
        return $this->memberCodeLocked;
    }

    public function autoFillMemberCode(?int $groupId, ?Set $set = null): void
    {
        if (! $groupId) {
            if ($set) {
                $set('member_code', null);
            }
            $this->data['member_code'] = null;
            $this->memberCodeLocked = true;
            $this->memberCodeIsAuto = false;

            return;
        }

        $group = Group::find($groupId);
        if (! $group) {
            if ($set) {
                $set('member_code', null);
            }
            $this->data['member_code'] = null;
            $this->memberCodeLocked = false;
            $this->memberCodeIsAuto = false;

            return;
        }

        $hierarchyService = app(GroupHierarchyService::class);
        $validation = $hierarchyService->validateChainIntegrity($group);

        if ($validation['status'] === 'no_code' || ! $validation['is_valid']) {
            if ($set) {
                $set('member_code', null);
            }
            $this->data['member_code'] = null;
            // Kelompok tanpa kode -> tidak dikunci, boleh dikosongkan atau diisi manual
            $this->memberCodeLocked = false;
            $this->memberCodeIsAuto = false;

            return;
        }

        try {
            $nextCode = $hierarchyService->generateNextMemberCode($group);
            if ($set) {
                $set('member_code', $nextCode);
            }
            $this->data['member_code'] = $nextCode;
            $this->memberCodeLocked = true;
            $this->memberCodeIsAuto = true;
        } catch (\Throwable $e) {
            if ($set) {
                $set('member_code', null);
            }
            $this->data['member_code'] = null;
            $this->memberCodeLocked = false;
            $this->memberCodeIsAuto = false;
        }
    }

    public function toggleMemberCodeLock(?Set $set = null): void
    {
        $groupId = $this->data['group_id'] ?? null;

        if ($this->memberCodeLocked) {
            // Unlock: ijinkan edit manual
            $this->memberCodeLocked = false;
            $this->memberCodeIsAuto = false;
        } else {
            // Lock: re-generate ID otomatis jika grup valid
            if ($groupId) {
                $this->autoFillMemberCode((int) $groupId, $set);
            } else {
                $this->memberCodeLocked = true;
            }
        }
    }

    protected function onValidationError(ValidationException $exception): void
    {
        // Jika ada kesalahan validasi pada member_code, buka lock agar user bisa koreksi
        if ($exception->validator && $exception->validator->errors()->has('data.member_code')) {
            $this->memberCodeLocked = false;
            $this->memberCodeIsAuto = false;
        }

        parent::onValidationError($exception);
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (QueryException $e) {
            $isDuplicate = $e->getCode() == 23000
                || str_contains($e->getMessage(), 'Duplicate entry')
                || str_contains($e->getMessage(), 'members_member_code_unique');

            if ($isDuplicate && ! empty($data['member_code']) && $this->memberCodeIsAuto && ! empty($data['group_id'])) {
                $group = Group::find($data['group_id']);
                if ($group) {
                    try {
                        $newCode = app(GroupHierarchyService::class)->generateNextMemberCode($group);
                        if ($newCode && $newCode !== $data['member_code']) {
                            $data['member_code'] = $newCode;
                            $this->data['member_code'] = $newCode;

                            $record = parent::handleRecordCreation($data);

                            Notification::make()
                                ->title('ID Otomatis Diperbarui')
                                ->body("ID sebelumnya telah digunakan pihak lain. Anggota berhasil disimpan dengan ID baru: {$newCode}")
                                ->warning()
                                ->send();

                            return $record;
                        }
                    } catch (\Throwable $retryException) {
                        // Jika retry gagal, lanjut buka lock
                    }
                }
            }

            // Buka lock agar user bisa revisi ID jika terjadi konflik DB
            $this->memberCodeLocked = false;
            $this->memberCodeIsAuto = false;

            if ($isDuplicate) {
                throw ValidationException::withMessages([
                    'data.member_code' => 'ID Anggota ini sudah terdaftar. Silakan gunakan ID lain atau kunci kembali untuk generate nomor baru.',
                ]);
            }

            throw $e;
        }
    }
}
