<?php

namespace App\Providers;

use App\Models\PayrollRecord;
use App\Models\RecruitmentCampaign;
use App\Models\TrainingCourse;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Policies\PayrollRecordPolicy;
use App\Policies\RecruitmentCampaignPolicy;
use App\Policies\TrainingCoursePolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        PayrollRecord::class => PayrollRecordPolicy::class,
        RecruitmentCampaign::class => RecruitmentCampaignPolicy::class,
        TrainingCourse::class => TrainingCoursePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::before(function (User $user, string $ability): bool|null {
            if ($this->isAdmin($user)) {
                return true;
            }

            return null;
        });

        Gate::define('permission', function (User $user, string $permission): bool {
            return $this->hasPermission($user, $permission);
        });
    }

    private function isAdmin(User $user): bool
    {
        $role = strtolower((string) ($user->VaiTro ?? $user->role ?? ''));

        return in_array($role, ['admin', 'administrator', 'quantri'], true);
    }

    private function hasPermission(User $user, string $permission): bool
    {
        $permissions = $user->permissions ?? $user->Quyen ?? [];

        if (is_string($permissions)) {
            $permissions = array_filter(array_map('trim', explode(',', $permissions)));
        }

        if (!is_array($permissions)) {
            return false;
        }

        return in_array($permission, $permissions, true);
    }
}
