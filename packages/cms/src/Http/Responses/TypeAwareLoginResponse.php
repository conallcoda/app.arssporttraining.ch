<?php

namespace Coda\Cms\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class TypeAwareLoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $homeByType = config('cms.home_by_type');

        if ($homeByType) {
            $type = auth()->user()->type->value;
            $home = $homeByType[$type] ?? $homeByType['*'] ?? config('cms.home', '/admin/dashboard');
        } else {
            $home = Fortify::redirects('login');
        }

        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended($home);
    }
}
