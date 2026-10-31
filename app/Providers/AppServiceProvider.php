<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Audit;
use App\Support\CurrentSite;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentSite::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureAuditing();
    }

    /**
     * Grant superadmins every ability.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user) => $user->isSuperadmin() ? true : null);
    }

    /**
     * Record authentication events in the audit log.
     */
    protected function configureAuditing(): void
    {
        Event::listen(Login::class, fn (Login $event) => Audit::record('auth.login', $event->user instanceof User ? $event->user : null));
        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user instanceof User) {
                Audit::record('auth.logout', $event->user);
            }
        });
        Event::listen(Failed::class, fn (Failed $event) => Audit::record('auth.failed', meta: [
            'email' => $event->credentials['email'] ?? null,
        ]));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
