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
            $request->session()->regenerate();

            $redirect = $this->sessionRedirectTarget($request);

            if ($redirect !== null) {
                return redirect()->to($redirect);
            }

            if ($this->isLoginRequest($request)) {
                return redirect()->to(config('cms.home', '/admin/dashboard'));
            }
        }

        return $next($request);
    }

    protected function sessionRedirectTarget(Request $request): ?string
    {
        $intended = $request->session()->pull('url.intended');

        return is_string($intended) && $intended !== ''
            ? $intended
            : null;
    }

    protected function isLoginRequest(Request $request): bool
    {
        return $request->is('login');
    }
}
