<?php

namespace App\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LivewireProfilerMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        DB::enableQueryLog();
        $start = microtime(true);

        $response = $next($request);

        $elapsed = round((microtime(true) - $start) * 1000, 1);
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        $queryTime = round(array_sum(array_column($queries, 'time')), 1);
        $memoryMb = round(memory_get_peak_usage(true) / 1024 / 1024, 1);

        $branch = 'unknown';
        $gitHead = base_path('.git/HEAD');
        if (file_exists($gitHead)) {
            $head = trim(file_get_contents($gitHead));
            if (str_starts_with($head, 'ref: refs/heads/')) {
                $branch = substr($head, 16);
            }
        }

        $component = 'unknown';
        $snapshot = $request->input('components.0.snapshot');
        if ($snapshot) {
            $decoded = json_decode($snapshot, true);
            $component = $decoded['memo']['name'] ?? 'unknown';
        }

        $calls = $request->input('components.0.calls', []);
        $methods = array_column($calls, 'method');

        $slowQueries = [];
        foreach ($queries as $q) {
            if ($q['time'] > 10) {
                $slowQueries[] = round($q['time'], 1).'ms: '.substr($q['query'], 0, 120);
            }
        }

        $safeBranch = preg_replace('/[^a-zA-Z0-9_-]/', '_', $branch);
        $profilePath = storage_path("logs/livewire-profile-{$safeBranch}.log");

        $responseSize = strlen($response->getContent());
        $responseSizeKb = round($responseSize / 1024, 1);

        $entry = json_encode([
            'time' => now()->format('H:i:s.v'),
            'branch' => $branch,
            'component' => $component,
            'methods' => $methods ?: null,
            'elapsed_ms' => $elapsed,
            'queries' => $queryCount,
            'query_time_ms' => $queryTime,
            'memory_mb' => $memoryMb,
            'response_kb' => $responseSizeKb,
            'slow_queries' => $slowQueries ?: null,
        ], JSON_UNESCAPED_SLASHES);

        file_put_contents($profilePath, $entry."\n", FILE_APPEND | LOCK_EX);

        return $response;
    }
}
