<?php

namespace App\Support\Profiling;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

class PlanGridProfiler
{
    private static bool $booted = false;

    private static ?bool $enabled = null;

    private static bool $queryLogging = false;

    private static int $queryCount = 0;

    private static float $queryTimeMs = 0.0;

    private static string $requestId = '';

    /** @var list<string> */
    private static array $stack = [];

    public static function enabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        $requestValue = request()?->query('profile_plan_grid');
        $sessionValue = null;
        $request = request();

        if ($request?->hasSession()) {
            if (in_array($requestValue, ['0', 'false', 'off'], true)) {
                $request->session()->forget('plan_grid_profile');
            } elseif (filled($requestValue)) {
                $request->session()->put('plan_grid_profile', $requestValue);
            }

            $sessionValue = $request->session()->get('plan_grid_profile');
        }

        self::$queryLogging = $requestValue === 'queries'
            || $sessionValue === 'queries'
            || filter_var(env('PLAN_GRID_PROFILE_QUERIES', false), FILTER_VALIDATE_BOOL);

        self::$enabled = (
            filled($requestValue)
            && ! in_array($requestValue, ['0', 'false', 'off'], true)
        )
            || filled($sessionValue)
            || filter_var(env('PLAN_GRID_PROFILE', false), FILTER_VALIDATE_BOOL);

        if (self::$enabled) {
            self::boot();
        }

        return self::$enabled;
    }

    public static function start(string $name, array $context = []): PlanGridProfileSpan
    {
        if (! self::enabled()) {
            return PlanGridProfileSpan::disabled();
        }

        $id = self::newId();
        $parentId = self::$stack[array_key_last(self::$stack)] ?? null;
        self::$stack[] = $id;

        return new PlanGridProfileSpan(
            id: $id,
            parentId: $parentId,
            name: $name,
            context: self::cleanContext($context),
            startMs: microtime(true) * 1000,
            startMemory: memory_get_usage(true),
            startPeakMemory: memory_get_peak_usage(true),
            startQueryCount: self::$queryCount,
            startQueryTimeMs: self::$queryTimeMs,
        );
    }

    public static function end(PlanGridProfileSpan $span, array $context = []): void
    {
        if (! self::enabled() || ! $span->enabled) {
            return;
        }

        $endMs = microtime(true) * 1000;

        if ((self::$stack[array_key_last(self::$stack)] ?? null) === $span->id) {
            array_pop(self::$stack);
        } else {
            self::$stack = array_values(array_filter(self::$stack, fn (string $id): bool => $id !== $span->id));
        }

        self::write([
            'type' => 'span',
            'id' => $span->id,
            'parent_id' => $span->parentId,
            'name' => $span->name,
            'duration_ms' => round($endMs - $span->startMs, 3),
            'query_count' => self::$queryCount - $span->startQueryCount,
            'query_time_ms' => round(self::$queryTimeMs - $span->startQueryTimeMs, 3),
            'memory_delta_kb' => round((memory_get_usage(true) - $span->startMemory) / 1024, 1),
            'memory_peak_delta_kb' => round((memory_get_peak_usage(true) - $span->startPeakMemory) / 1024, 1),
            'memory_mb' => round(memory_get_usage(true) / 1048576, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            'context' => array_merge($span->context, self::cleanContext($context)),
        ]);
    }

    public static function mark(string $name, array $context = []): void
    {
        if (! self::enabled()) {
            return;
        }

        self::write([
            'type' => 'mark',
            'name' => $name,
            'parent_id' => self::$stack[array_key_last(self::$stack)] ?? null,
            'query_count_total' => self::$queryCount,
            'query_time_ms_total' => round(self::$queryTimeMs, 3),
            'memory_mb' => round(memory_get_usage(true) / 1048576, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            'context' => self::cleanContext($context),
        ]);
    }

    public static function measure(string $name, array $context, callable $callback): mixed
    {
        $span = self::start($name, $context);

        try {
            $result = $callback();

            return $result;
        } finally {
            $extra = [];

            if (isset($result) && is_countable($result)) {
                $extra['result_count'] = count($result);
            }

            self::end($span, $extra);
        }
    }

    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;
        self::$requestId = substr(bin2hex(random_bytes(8)), 0, 12);

        self::write([
            'type' => 'request_start',
            'method' => request()?->method(),
            'path' => request()?->path(),
            'livewire' => request()?->path() === 'livewire/update',
            'query' => request()?->query(),
            'context' => [],
        ]);

        DB::listen(function (QueryExecuted $query): void {
            self::$queryCount++;
            self::$queryTimeMs += (float) $query->time;

            if (! self::$queryLogging) {
                return;
            }

            self::write([
                'type' => 'query',
                'parent_id' => self::$stack[array_key_last(self::$stack)] ?? null,
                'time_ms' => round((float) $query->time, 3),
                'connection' => $query->connectionName,
                'sql' => $query->sql,
                'bindings' => self::cleanContext($query->bindings),
            ]);
        });
    }

    private static function write(array $payload): void
    {
        $payload = array_merge([
            'ts' => now()->toIso8601String(),
            'request_id' => self::$requestId,
        ], $payload);

        $path = storage_path('logs/plan-grid-profile.log');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        file_put_contents($path, json_encode($payload, JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private static function newId(): string
    {
        return substr(bin2hex(random_bytes(8)), 0, 12);
    }

    private static function cleanContext(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (is_array($value)) {
            $clean = [];

            foreach ($value as $key => $item) {
                $clean[$key] = self::cleanContext($item);
            }

            return $clean;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if (is_object($value)) {
            return [
                'class' => $value::class,
                'id' => $value->id ?? null,
            ];
        }

        return get_debug_type($value);
    }
}

class PlanGridProfileSpan
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $parentId,
        public readonly string $name,
        public readonly array $context,
        public readonly float $startMs,
        public readonly int $startMemory,
        public readonly int $startPeakMemory,
        public readonly int $startQueryCount,
        public readonly float $startQueryTimeMs,
        public readonly bool $enabled = true,
    ) {}

    public static function disabled(): self
    {
        return new self('', null, '', [], 0.0, 0, 0, 0, 0.0, false);
    }
}
