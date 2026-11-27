<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureUserIsActive::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'subscription.active' => EnsureSubscriptionActive::class,
            'active' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // The mobile API's errors: {code, message, errors?} with Arabic messages (docs/api.md).
        $exceptions->render(fn (Throwable $e, Request $request) => $request->is('api/*') ? ApiException::from($e)?->render() : null);

        // Arabic error pages instead of Laravel's English ones. 500 and 503 keep the
        // debug page while APP_DEBUG is on, so errors stay readable in development.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if (! in_array($status, [403, 404, 419, 500, 503], true)
                || $request->is('api/*')
                || (in_array($status, [500, 503], true) && config('app.debug'))) {
                return $response;
            }

            // An expired form in an open page: back to it with a toast rather than a full error page.
            if ($status === 419 && $request->header('X-Inertia')) {
                return back()->with('error', 'انتهت صلاحية الصفحة. حاول مرة أخرى.');
            }

            return Inertia::render('Error', ['status' => $status, 'appName' => config('app.name')])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
