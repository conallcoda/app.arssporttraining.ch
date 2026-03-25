<?php

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\ComponentHook;

class CalendarProfilerHook extends ComponentHook
{
    private static bool $queryLogEnabled = false;

    public function boot(): void
    {
        if (! self::$queryLogEnabled) {
            DB::enableQueryLog();
            self::$queryLogEnabled = true;
        }

        $logPath = storage_path('logs/profiler-debug.log');
        file_put_contents($logPath, 'boot: '.$this->component->getName()."\n", FILE_APPEND);
    }

    public function dehydrate($context): void
    {
        $name = $this->component->getName();

        $logPath = storage_path('logs/profiler-debug.log');
        file_put_contents($logPath, 'dehydrate: '.$name."\n", FILE_APPEND);

        if (! str_starts_with($name, 'training.calendar')) {
            return;
        }

        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        $queryTime = array_sum(array_column($queries, 'time'));
        $memoryMb = round(memory_get_peak_usage(true) / 1024 / 1024, 1);

        $branch = 'unknown';
        $gitHead = base_path('.git/HEAD');
        if (file_exists($gitHead)) {
            $head = trim(file_get_contents($gitHead));
            if (str_starts_with($head, 'ref: refs/heads/')) {
                $branch = substr($head, 16);
            }
        }

        $safeBranch = preg_replace('/[^a-zA-Z0-9_-]/', '_', $branch);
        $profilePath = storage_path("logs/livewire-profile-{$safeBranch}.log");

        $entry = json_encode([
            'time' => now()->format('H:i:s.v'),
            'branch' => $branch,
            'component' => $name,
            'queries' => $queryCount,
            'query_time_ms' => round($queryTime, 1),
            'memory_mb' => $memoryMb,
        ], JSON_UNESCAPED_SLASHES);

        file_put_contents($profilePath, $entry."\n", FILE_APPEND | LOCK_EX);
    }
}
