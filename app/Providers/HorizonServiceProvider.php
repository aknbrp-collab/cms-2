<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        if ($email = config('cms.alert_email')) {
            Horizon::routeMailNotificationsTo($email);
        }
    }

    /**
     * Only superadmins can open the Horizon dashboard (outside the local environment).
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null) => $user?->isSuperadmin() === true);
    }
}
