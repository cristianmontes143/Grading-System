<?php

namespace App\Providers;

use App\Models\Section;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Policies\SectionPolicy;
use App\Policies\TeacherAssignmentPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Section::class => SectionPolicy::class,
        TeacherAssignment::class => TeacherAssignmentPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function (User $user, string $ability): ?bool {
            if (! $user->is_active) {
                return false;
            }

            return $user->isAdmin() ? true : null;
        });

        Gate::after(function (User $user, string $ability, ?bool $result): ?bool {
            if ($result !== null) {
                return $result;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }
}
