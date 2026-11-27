<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** A staff member the owner disabled is signed out on their next request (remember-me included). */
class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            // The mobile API (route middleware "active", after auth:sanctum): the device's token is revoked.
            if ($request->is('api/*')) {
                $request->user()->currentAccessToken()?->delete();

                throw new ApiException('account_disabled', 'تم إيقاف حسابك. تواصل مع صاحب المخبز.', 401);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'تم إيقاف حسابك. تواصل مع صاحب المخبز.');
        }

        return $next($request);
    }
}
