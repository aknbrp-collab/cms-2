<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetAdminSite;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SwitchSiteController extends Controller
{
    /**
     * Switch the admin panel to a site, or to all sites.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $siteId = $request->validate(['site' => ['required', 'string']])['site'];

        if ($siteId === SetAdminSite::ALL) {
            abort_unless($user->canAccessAllSites(), 403);
        } else {
            abort_unless(Str::isUuid($siteId), 404);
            $site = Site::query()->findOrFail($siteId);
            abort_unless($user->canAccessSite($site), 403);
        }

        $request->session()->put(SetAdminSite::SESSION_KEY, $siteId);

        return back();
    }
}
