<?php

use App\Http\Controllers\HealthController;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        using: function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Health check registered outside web/api groups so it doesn't
            // load session, cookie or CSRF middleware. External monitors
            // hit it without state and it must work regardless of session
            // driver config.
            Route::get('/up', HealthController::class);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Translate Postgres CHECK constraint violations (SQLSTATE 23514)
        // into 422 responses so range/integrity rules enforced at the DB
        // level still surface as validation errors to API clients.
        // Form Requests catch the common cases first; this is the
        // defense-in-depth fallback for any path that bypasses them.
        $exceptions->render(function (QueryException $e, $request) {
            if ($request->expectsJson() && $e->getCode() === '23514') {
                return response()->json([
                    'message' => 'Los datos no cumplen una restricción de la base de datos.',
                    'errors' => [],
                ], 422);
            }
        });
    })->create();
