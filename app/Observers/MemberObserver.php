<?php

namespace App\Observers;

use App\Models\AgeGroup;
use App\Models\Group;
use App\Models\Member;
use App\Services\Groups\GroupHierarchyService;
use Illuminate\Support\Facades\Storage;

class MemberObserver
{
    /**
     * Handle the Member "saving" event.
     */
    public function saving(Member $member): void
    {
        // 1. Auto-uppercase full_name
        if ($member->full_name) {
            $member->full_name = strtoupper($member->full_name);
        }

        // 2. Member code handling
        if ($member->exists && ! empty($member->getOriginal('member_code')) && empty($member->member_code)) {
            // Safety guard: prevent accidental deletion or unintentional regeneration of an existing member code
            $member->member_code = $member->getOriginal('member_code');
        } elseif (empty($member->member_code) && $member->group_id) {
            $group = $member->group ?? Group::find($member->group_id);
            if ($group) {
                $service = app(GroupHierarchyService::class);
                $nextCode = $service->generateNextMemberCode($group);
                if ($nextCode) {
                    $member->member_code = $nextCode;
                }
            }
        }

        if ($member->birth_date) {
            $birthDate = \Carbon\Carbon::parse($member->birth_date);
            $age = $birthDate->age;
            $member->age = $age;

            // Automatic category matching if not manually set OR if birth_date is newly set / updated
            if (! $member->age_group_id || $member->isDirty('birth_date')) {
                $matchingGroup = AgeGroup::where('min_age', '<=', $age)
                    ->where(function ($query) use ($age) {
                        $query->where('max_age', '>=', $age)
                            ->orWhereNull('max_age');
                    })
                    ->first();

                if ($matchingGroup) {
                    $member->age_group_id = $matchingGroup->id;
                }
            }
        }
    }

    /**
     * Handle the Member "created" event.
     */
    public function created(Member $member): void
    {
        $this->generateQrCode($member);
    }

    /**
     * Handle the Member "updated" event.
     */
    public function updated(Member $member): void
    {
        if ($member->isDirty('member_code')) {
            $this->generateQrCode($member);
        }
    }

    /**
     * Handle the Member "deleted" event.
     */
    public function deleted(Member $member): void
    {
        if ($member->qr_code_path) {
            Storage::disk('public')->delete($member->qr_code_path);
        }
    }

    /**
     * Generate QR Code for the member.
     */
    protected function generateQrCode(Member $member): void
    {
        if (! $member->member_code) {
            if ($member->qr_code_path) {
                Storage::disk('public')->delete($member->qr_code_path);
                $member->updateQuietly(['qr_code_path' => null]);
            }

            return;
        }

        // Standardized name and size
        $fileName = 'qrcodes/'.$member->member_code.'.png';

        // Ensure directory exists
        if (! Storage::disk('public')->exists('qrcodes')) {
            Storage::disk('public')->makeDirectory('qrcodes');
        }

        // Generate REAL PNG using custom GD-based generator
        $qrContent = \App\Services\QrGenerator::generatePng($member->member_code, 500, 2);

        // Save file
        Storage::disk('public')->put($fileName, $qrContent);

        // Update path in database
        $member->updateQuietly(['qr_code_path' => $fileName]);
    }
}
