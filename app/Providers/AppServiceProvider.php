<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\SetAdminSite;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\SeoMeta;
use App\Models\Site;
use App\Models\User;
use App\Policies\MediaAssetPolicy;
use App\Policies\PagePolicy;
use App\Policies\PostPolicy;
use App\Support\Audit;
use App\Support\CurrentSite;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

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
        $this->configureMorphMap();
        $this->configureLivewire();
        $this->configureAuthorization();
        $this->configureAuditing();
    }

    /**
     * Grant superadmins every ability.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user) => $user->isSuperadmin() ? true : null);

        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(MediaAsset::class, MediaAssetPolicy::class);

        Gate::define('review-content', fn (User $user) => $user->canAny(['posts.publish', 'pages.publish']));
    }

    /**
     * Re-run the admin middleware on Livewire update requests, so component
     * actions see the same user checks and current site as the page itself.
     */
    protected function configureLivewire(): void
    {
        Livewire::addPersistentMiddleware([
            EnsureAdminAccess::class,
            SetAdminSite::class,
        ]);
    }

    /**
     * Store short, stable type names in polymorphic columns instead of class names.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'site' => Site::class,
            'post' => Post::class,
            'page' => Page::class,
            'category' => Category::class,
            'media_asset' => MediaAsset::class,
            'menu' => Menu::class,
            'menu_item' => MenuItem::class,
            'seo_meta' => SeoMeta::class,
        ]);
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
