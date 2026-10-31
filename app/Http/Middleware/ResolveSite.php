<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Support\CurrentSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the public site from the request host.
 */
class ResolveSite
{
    public function __construct(protected CurrentSite $currentSite) {}

    public function handle(Request $request, Closure $next): Response
    {
        $site = Site::query()
            ->where('domain', $request->getHost())
            ->where('is_active', true)
            ->first();

        abort_if($site === null, 404);

        $this->currentSite->set($site);

        return $next($request);
    }
}
