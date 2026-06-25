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

        $email = $this->configuredEmail();

        if ($email === null) {
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

    protected function configuredEmail(): ?string
    {
        $value = config('cms.auto_login');

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false) {
            return null;
        }

        return $value;
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
