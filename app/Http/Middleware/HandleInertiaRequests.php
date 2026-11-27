<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /** The route names the page gets (config/ziggy.php groups, `@routes` in app.blade.php). */
    public static function routeGroup(): string
    {
        return auth()->user()?->isSuperAdmin() ? 'admin' : (auth()->check() ? 'panel' : 'guest');
    }

    /**
     * Signing in or out changes the route group, so the version differs and Inertia reloads the
     * page in full (one 409) instead of keeping the previous role's route names.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request).'|'.self::routeGroup();
    }

    /**
     * Define the props that are shared by default.
     *
     * Only the fields the layout needs: never pass whole models to React.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            // The server's date, used as the default in date fields so they match the dashboard's "today".
            'currentDate' => fn () => local_today()->toDateString(),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ] : null,
                'bakery' => $user?->bakery ? ['name' => $user->bakery->name] : null,
                // Only hides links and buttons: every permission is checked again in the server.
                'permissions' => $user ? $user->grantedPermissions() : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
