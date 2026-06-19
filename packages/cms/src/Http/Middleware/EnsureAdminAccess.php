<?php

namespace Coda\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedTypes = config('cms.admin_user_types');

        if ($allowedTypes === null) {
            return $next($request);
        }

        if (! in_array($request->user()->type->value, $allowedTypes, true)) {
            abort(403);
        }

        return $next($request);
    }
}
