<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Models\User;
use App\Support\CurrentSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apply the site chosen in the admin site switcher to the current request.
 *
 * The session holds a site id, or "all" for users who may see every site.
 */
class SetAdminSite
{
    public const SESSION_KEY = 'admin_site';

    public const ALL = 'all';

    public function __construct(protected CurrentSite $currentSite) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();
        $sites = $user->accessibleSites();
        $selected = $request->session()->get(self::SESSION_KEY);

        $site = match (true) {
            $selected === self::ALL && $user->canAccessAllSites() => null,
            $selected !== null && $sites->contains('id', $selected) => $sites->firstWhere('id', $selected),
            $user->canAccessAllSites() => null,
            default => $sites->first(),
        };

        abort_if($site === null && ! $user->canAccessAllSites(), 403);

        $request->session()->put(self::SESSION_KEY, $site instanceof Site ? $site->id : self::ALL);
        $this->currentSite->set($site);

        return $next($request);
    }
}
