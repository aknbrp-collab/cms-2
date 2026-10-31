<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\User;
use App\Social\SocialException;
use App\Social\SocialManager;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * OAuth connect flow for social accounts. The state parameter (stored in the
 * session with the target site and PKCE verifier) protects against CSRF.
 */
class SocialConnectController extends Controller
{
    protected const SESSION_KEY = 'social_oauth';

    public function redirect(Request $request, string $provider, SocialManager $social): RedirectResponse
    {
        $provider = SocialProvider::tryFrom($provider) ?? abort(404);
        $site = current_site();

        abort_unless($request->user()?->can('social_accounts.connect'), 403);

        if ($site === null) {
            return redirect()->route('admin.social.accounts')->with('error', __('Choose a site in the sidebar first.'));
        }

        try {
            $driver = $social->forProvider($provider);
        } catch (SocialException $e) {
            return redirect()->route('admin.social.accounts')->with('error', $e->getMessage());
        }

        $state = Str::random(40);
        $verifier = Str::random(64);

        $request->session()->put(self::SESSION_KEY, [
            'state' => $state,
            'verifier' => $verifier,
            'site_id' => $site->id,
            'provider' => $provider->value,
        ]);

        return redirect()->away($driver->authorizationUrl($state, $verifier));
    }

    public function callback(Request $request, string $app, SocialManager $social): RedirectResponse
    {
        abort_unless(in_array($app, ['meta', 'tiktok'], true), 404);

        /** @var array{state: string, verifier: string, site_id: string, provider: string}|null $pending */
        $pending = $request->session()->pull(self::SESSION_KEY);

        if ($pending === null || ! hash_equals($pending['state'], (string) $request->query('state'))) {
            return redirect()->route('admin.social.accounts')->with('error', __('The connection request expired. Please try again.'));
        }

        if ($request->filled('error')) {
            return redirect()->route('admin.social.accounts')->with('error', __('Connection cancelled: :reason', ['reason' => $request->query('error_description', $request->query('error'))]));
        }

        /** @var User $user */
        $user = $request->user();
        $site = Site::query()->findOrFail($pending['site_id']);
        abort_unless($user->can('social_accounts.connect') && $user->canAccessSite($site), 403);

        try {
            $accounts = $social->forApp($app, SocialProvider::from($pending['provider']))
                ->connect((string) $request->query('code'), $pending['verifier'], $site, $user);
        } catch (SocialException $e) {
            return redirect()->route('admin.social.accounts')->with('error', $e->getMessage());
        }

        foreach ($accounts as $account) {
            Audit::record('social.connected', $account, meta: ['provider' => $account->provider->value]);
        }

        return redirect()->route('admin.social.accounts')->with('status', trans_choice(':count account connected.|:count accounts connected.', count($accounts)));
    }
}
