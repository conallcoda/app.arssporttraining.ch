<?php

namespace Coda\Cms\Http\Middleware;

use Closure;
use Coda\Cms\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLoginInDevelopment
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction() || Auth::check()) {
            return $next($request);
        }

        $email = config('cms.auto_login');

        if (! $email) {
            return $next($request);
        }

        $userModel = config('cms.models.user', User::class);

        $user = $userModel::query()->where('email', $email)->first();

        if ($user) {
            Auth::login($user);
        }

        return $next($request);
    }
}
