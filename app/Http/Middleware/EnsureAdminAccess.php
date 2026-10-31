<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow only active users holding at least one CMS role into the admin panel.
 */
class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('This account has been deactivated.')]);
        }

        abort_if($user->cmsRoles() === [], 403, __('Your account has no CMS role yet. Ask a superadmin to assign one.'));

        return $next($request);
    }
}
