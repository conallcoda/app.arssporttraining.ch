<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ClientJsLogController
{
    public function __invoke(Request $request): Response
    {
        abort_unless((bool) config('logging.client_js.enabled'), 404);
        abort_if(strlen($request->getContent()) > config('logging.client_js.max_bytes'), 413);
        abort_unless($this->isSameHost($request), 403);

        Log::channel('client_js')->info('client-js', [
            'ip' => $request->ip(),
            'payload' => $request->json()->all(),
            'received_at' => now()->toISOString(),
        ]);

        return response()->noContent();
    }

    private function isSameHost(Request $request): bool
    {
        $source = $request->headers->get('origin') ?: $request->headers->get('referer');

        if (! $source) {
            return true;
        }

        return parse_url($source, PHP_URL_HOST) === $request->getHost();
    }
}
