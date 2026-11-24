<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\AgeGroup;
use App\Models\Attendance;
use App\Models\CardTemplate;
use App\Models\Group;
use App\Models\Level;
use App\Models\Meeting;
use App\Models\Member;
use App\Models\MemberPosition;
use App\Models\PositionCategory;
use App\Models\User;
use App\Observers\MemberObserver;
use App\Policies\ActivityLogPolicy;
use App\Policies\AgeGroupPolicy;
use App\Policies\AttendancePolicy;
use App\Policies\CardTemplatePolicy;
use App\Policies\GroupPolicy;
use App\Policies\LevelPolicy;
use App\Policies\MeetingPolicy;
use App\Policies\MemberPolicy;
use App\Policies\MemberPositionPolicy;
use App\Policies\PositionCategoryPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Member::observe(MemberObserver::class);

        Gate::before(function ($user, $ability) {
            // Biarkan policy menjalankan proteksi khusus (anti-self-delete & safety net)
            if (in_array($ability, ['delete', 'forceDelete'], true)) {
                return null;
            }

            return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin() ? true : null;
        });

        Gate::policy(Member::class, MemberPolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
        Gate::policy(Meeting::class, MeetingPolicy::class);
        Gate::policy(Attendance::class, AttendancePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(MemberPosition::class, MemberPositionPolicy::class);
        Gate::policy(ActivityLog::class, ActivityLogPolicy::class);
        Gate::policy(CardTemplate::class, CardTemplatePolicy::class);
        Gate::policy(Level::class, LevelPolicy::class);
        Gate::policy(AgeGroup::class, AgeGroupPolicy::class);
        Gate::policy(PositionCategory::class, PositionCategoryPolicy::class);
    }
}
