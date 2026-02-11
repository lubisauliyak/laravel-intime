<?php

namespace App\Observers;

use App\Models\Member;
use App\Models\AgeGroup;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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

        if ($member->birth_date) {
            $birthDate = \Carbon\Carbon::parse($member->birth_date);
            $age = $birthDate->age;
            $member->age = $age;

            // Automatic category matching if not manually set OR if it's a new record
            if (!$member->age_group_id) {
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
        if (!$member->member_code) {
            if ($member->qr_code_path) {
                Storage::disk('public')->delete($member->qr_code_path);
                $member->updateQuietly(['qr_code_path' => null]);
            }
            return;
        }

        $fileName = 'qrcodes/' . $member->member_code . '.png';
        
        // Ensure directory exists
        if (!Storage::disk('public')->exists('qrcodes')) {
            Storage::disk('public')->makeDirectory('qrcodes');
        }

        // Generate QR code
        $qrCode = QrCode::format('png')
            ->size(500)
            ->margin(2)
            ->errorCorrection('H')
            ->generate($member->member_code);

        // Save file
        Storage::disk('public')->put($fileName, $qrCode);

        // Update path in database
        $member->updateQuietly(['qr_code_path' => $fileName]);
    }
}
