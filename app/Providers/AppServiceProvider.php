<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Attendance;
use App\Models\MemberPosition;
use App\Policies\AttendancePolicy;
use App\Policies\MemberPositionPolicy;

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
        \App\Models\Member::observe(\App\Observers\MemberObserver::class);

        Gate::before(function ($user, $ability) {
            return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin() ? true : null;
        });

        Gate::policy(Attendance::class, AttendancePolicy::class);
        Gate::policy(MemberPosition::class, MemberPositionPolicy::class);
        Gate::policy(\App\Models\ActivityLog::class, \App\Policies\ActivityLogPolicy::class);
    }
}
