<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    use HasPageShield;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $ref = $this->getReferenceMeeting();
        $dateStr = $ref ? ($ref->meeting_date->isToday() ? 'Hari Ini' : $ref->meeting_date->format('d/m/Y')) : now()->format('d/m/Y');
        
        return $ref ? "Dasbor, {$ref->name} ({$dateStr})" : 'Dasbor';
    }

    public function getSubheading(): ?string
    {
        return "Selamat datang, " . auth()->user()->name . "!";
    }

    private function getReferenceMeeting(): ?\App\Models\Meeting
    {
        $user = auth()->user();
        $query = \App\Models\Meeting::where('meeting_date', '<=', now()->toDateString());
        
        if (!$user->isSuperAdmin() && $user->group_id) {
            $allowedMeetingGroupIds = array_merge(
                [$user->group_id],
                $user->group->getAllAncestorIds()
            );
            $query->whereIn('group_id', $allowedMeetingGroupIds);
        }
        
        return $query->latest('meeting_date')->first();
    }
}
