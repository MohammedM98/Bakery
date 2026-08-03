<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bakery = $request->user()?->bakery;

        if (! $bakery || ! $bakery->isSubscriptionActive()) {
            return redirect()->route('panel.subscription.expired');
        }

        return $next($request);
    }
}
