<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\LogRequestTiming::class);
        $middleware->validateCsrfTokens(except: [
            'client-js-log',
        ]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(function () {
            $homeByType = config('cms.home_by_type');

            if ($homeByType) {
                $type = auth()->user()->type->value;

                return $homeByType[$type] ?? $homeByType['*'] ?? config('cms.home', '/admin/dashboard');
            }

            return config('cms.home', '/admin/dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login');
        });
    })->create();
