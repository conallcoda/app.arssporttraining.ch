<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use SplFileObject;

class SummarizePlanGridProfileCommand extends Command
{
    protected $signature = 'logs:plan-grid-profile
        {path? : Profile log path. Defaults to storage/logs/plan-grid-profile.log}
        {--path= : Profile log path. Defaults to storage/logs/plan-grid-profile.log}
        {--request=latest : Request id, request id prefix, latest, or latest-livewire}
        {--list : List requests instead of showing one request in detail}
        {--top=25 : Number of rows to show per section}
        {--min=0 : Minimum duration in ms for tree rows}
        {--tree : Show the span tree for the selected request}
        {--queries : Show grouped query fingerprints when query logging was enabled}
        {--json : Emit JSON instead of tables}';

    protected $description = 'Summarize plan grid profile JSONL logs by request, span, self time, and query fingerprint.';

    public function handle(): int
    {
        $path = $this->resolvePath(
            $this->option('path')
                ?: $this->argument('path')
                ?: storage_path('logs/plan-grid-profile.log')
        );
        $top = max(1, (int) $this->option('top'));

        if (! is_file($path)) {
            $this->error("Profile log not found: {$path}");

            return self::FAILURE;
        }

        $requests = $this->readRequests($path);

        if ($requests === []) {
            $this->warn("No profile entries found in {$path}.");

            return self::SUCCESS;
        }

        $summaries = $this->requestSummaries($requests);

        if ((bool) $this->option('list')) {
            return $this->renderRequestList($summaries, $path, $top);
        }

        $request = $this->selectRequest($requests, (string) $this->option('request'));

        if ($request === null) {
            $this->error('Request not found. Use --list to see available request ids.');

            return self::FAILURE;
        }

        $analysis = $this->analyzeRequest($request);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->renderRequestAnalysis($analysis, $path, $top);

        if ((bool) $this->option('tree')) {
            $this->renderTree($analysis, (float) $this->option('min'));
        }

        if ((bool) $this->option('queries')) {
            $this->renderQueryGroups($analysis, $top);
        }

        return self::SUCCESS;
    }

    /** @return array<string, array<string, mixed>> */
    private function readRequests(string $path): array
    {
        $requests = [];
        $file = new SplFileObject($path);
        $lineNumber = 0;

        while (! $file->eof()) {
            $lineNumber++;
            $line = trim((string) $file->fgets());

            if ($line === '') {
                continue;
            }

            $entry = json_decode($line, true);

            if (! is_array($entry) || ! isset($entry['request_id'])) {
                continue;
            }

            $requestId = (string) $entry['request_id'];
            $requests[$requestId] ??= [
                'id' => $requestId,
                'request' => null,
                'entries' => [],
                'spans' => [],
                'marks' => [],
                'queries' => [],
                'first_ts' => $entry['ts'] ?? null,
                'last_ts' => $entry['ts'] ?? null,
                'line_start' => $lineNumber,
                'line_end' => $lineNumber,
            ];

            $entry['_line'] = $lineNumber;
            $requests[$requestId]['entries'][] = $entry;
            $requests[$requestId]['first_ts'] = min((string) ($requests[$requestId]['first_ts'] ?? $entry['ts']), (string) ($entry['ts'] ?? ''));
            $requests[$requestId]['last_ts'] = max((string) ($requests[$requestId]['last_ts'] ?? $entry['ts']), (string) ($entry['ts'] ?? ''));
            $requests[$requestId]['line_end'] = $lineNumber;

            match ($entry['type'] ?? null) {
                'request_start' => $requests[$requestId]['request'] = $entry,
                'span' => $requests[$requestId]['spans'][] = $entry,
                'mark' => $requests[$requestId]['marks'][] = $entry,
                'query' => $requests[$requestId]['queries'][] = $entry,
                default => null,
            };
        }

        return $requests;
    }

    /** @param array<string, array<string, mixed>> $requests */
    private function requestSummaries(array $requests): array
    {
        $summaries = [];

        foreach ($requests as $request) {
            $analysis = $this->analyzeRequest($request, includeHeavySections: false);
            $summaries[] = $analysis['summary'];
        }

        usort($summaries, fn (array $a, array $b): int => strcmp((string) $b['last_ts'], (string) $a['last_ts']));

        return $summaries;
    }

    /** @param array<string, array<string, mixed>> $requests */
    private function selectRequest(array $requests, string $selector): ?array
    {
        if ($selector === 'latest' || $selector === '') {
            usort($requests, fn (array $a, array $b): int => strcmp((string) $b['last_ts'], (string) $a['last_ts']));

            return $requests[0] ?? null;
        }

        if ($selector === 'latest-livewire') {
            $livewire = array_values(array_filter(
                $requests,
                fn (array $request): bool => (bool) data_get($request, 'request.livewire', false)
                    || str_contains((string) data_get($request, 'request.path', ''), 'livewire')
                    || str_contains((string) data_get($request, 'request.path', ''), 'update'),
            ));
            usort($livewire, fn (array $a, array $b): int => strcmp((string) $b['last_ts'], (string) $a['last_ts']));

            return $livewire[0] ?? null;
        }

        if (isset($requests[$selector])) {
            return $requests[$selector];
        }

        $matches = array_values(array_filter(
            $requests,
            fn (array $request): bool => str_starts_with((string) $request['id'], $selector),
        ));

        return count($matches) === 1 ? $matches[0] : null;
    }

    /** @param array<string, mixed> $request */
    private function analyzeRequest(array $request, bool $includeHeavySections = true): array
    {
        $spans = array_values($request['spans'] ?? []);
        $spanById = [];
        $childrenByParent = [];

        foreach ($spans as $index => $span) {
            $spans[$index]['duration_ms'] = (float) ($span['duration_ms'] ?? 0);
            $spans[$index]['query_count'] = (int) ($span['query_count'] ?? 0);
            $spans[$index]['query_time_ms'] = (float) ($span['query_time_ms'] ?? 0);
            $spanById[(string) ($span['id'] ?? '')] = $spans[$index];
            $parentId = $span['parent_id'] ?? null;
            if ($parentId !== null) {
                $childrenByParent[(string) $parentId][] = $spans[$index];
            }
        }

        foreach ($spans as $index => $span) {
            $childDuration = array_sum(array_map(
                fn (array $child): float => (float) ($child['duration_ms'] ?? 0),
                $childrenByParent[(string) ($span['id'] ?? '')] ?? [],
            ));
            $spans[$index]['self_ms'] = max(0, (float) ($span['duration_ms'] ?? 0) - $childDuration);
            $spanById[(string) ($span['id'] ?? '')] = $spans[$index];
        }

        $roots = array_values(array_filter(
            $spans,
            fn (array $span): bool => ($span['parent_id'] ?? null) === null || ! isset($spanById[(string) $span['parent_id']]),
        ));

        $summary = [
            'request_id' => $request['id'],
            'first_ts' => $request['first_ts'],
            'last_ts' => $request['last_ts'],
            'method' => data_get($request, 'request.method'),
            'path' => data_get($request, 'request.path'),
            'livewire' => (bool) data_get($request, 'request.livewire', false),
            'query' => data_get($request, 'request.query', []),
            'lines' => count($request['entries'] ?? []),
            'line_start' => $request['line_start'],
            'line_end' => $request['line_end'],
            'span_count' => count($spans),
            'mark_count' => count($request['marks'] ?? []),
            'query_line_count' => count($request['queries'] ?? []),
            'root_duration_ms' => round(array_sum(array_map(fn (array $span): float => (float) $span['duration_ms'], $roots)), 3),
            'root_query_count' => array_sum(array_map(fn (array $span): int => (int) $span['query_count'], $roots)),
            'root_query_time_ms' => round(array_sum(array_map(fn (array $span): float => (float) $span['query_time_ms'], $roots)), 3),
            'peak_memory_mb' => round(max(array_map(fn (array $span): float => (float) ($span['memory_peak_mb'] ?? 0), $spans ?: [['memory_peak_mb' => 0]])), 2),
        ];

        if (! $includeHeavySections) {
            return ['summary' => $summary];
        }

        return [
            'summary' => $summary,
            'top_inclusive' => $this->topSpans($spans, 'duration_ms'),
            'top_self' => $this->topSpans($spans, 'self_ms'),
            'top_queries' => $this->topSpans($spans, 'query_count'),
            'by_name' => $this->aggregateSpansByName($spans),
            'tree' => $this->buildTree($roots, $childrenByParent),
            'query_groups' => $this->aggregateQueries($request['queries'] ?? [], $spanById),
        ];
    }

    private function topSpans(array $spans, string $metric): array
    {
        usort($spans, fn (array $a, array $b): int => ($b[$metric] ?? 0) <=> ($a[$metric] ?? 0));

        return array_map(fn (array $span): array => $this->spanRow($span), $spans);
    }

    private function aggregateSpansByName(array $spans): array
    {
        $groups = [];

        foreach ($spans as $span) {
            $name = (string) ($span['name'] ?? 'unknown');
            $groups[$name] ??= [
                'name' => $name,
                'count' => 0,
                'total_ms' => 0.0,
                'self_ms' => 0.0,
                'max_ms' => 0.0,
                'avg_ms' => 0.0,
                'queries' => 0,
                'query_time_ms' => 0.0,
                'contexts' => [],
            ];

            $groups[$name]['count']++;
            $groups[$name]['total_ms'] += (float) ($span['duration_ms'] ?? 0);
            $groups[$name]['self_ms'] += (float) ($span['self_ms'] ?? 0);
            $groups[$name]['max_ms'] = max($groups[$name]['max_ms'], (float) ($span['duration_ms'] ?? 0));
            $groups[$name]['queries'] += (int) ($span['query_count'] ?? 0);
            $groups[$name]['query_time_ms'] += (float) ($span['query_time_ms'] ?? 0);
            $context = $this->importantContext($span['context'] ?? []);
            if ($context !== []) {
                $groups[$name]['contexts'][json_encode($context)] = $context;
            }
        }

        foreach ($groups as &$group) {
            $group['total_ms'] = round($group['total_ms'], 3);
            $group['self_ms'] = round($group['self_ms'], 3);
            $group['max_ms'] = round($group['max_ms'], 3);
            $group['avg_ms'] = round($group['total_ms'] / max(1, $group['count']), 3);
            $group['query_time_ms'] = round($group['query_time_ms'], 3);
            $group['contexts'] = array_slice(array_values($group['contexts']), 0, 3);
        }

        usort($groups, fn (array $a, array $b): int => $b['total_ms'] <=> $a['total_ms']);

        return array_values($groups);
    }

    private function aggregateQueries(array $queries, array $spanById): array
    {
        $groups = [];

        foreach ($queries as $query) {
            $fingerprint = $this->fingerprintSql((string) ($query['sql'] ?? ''));
            $parentId = (string) ($query['parent_id'] ?? '');
            $parentName = $spanById[$parentId]['name'] ?? null;
            $key = $fingerprint.'|'.($parentName ?? 'unknown');

            $groups[$key] ??= [
                'count' => 0,
                'total_ms' => 0.0,
                'max_ms' => 0.0,
                'avg_ms' => 0.0,
                'parent' => $parentName ?? 'unknown',
                'sql' => $fingerprint,
                'sample_bindings' => $query['bindings'] ?? [],
            ];

            $time = (float) ($query['time_ms'] ?? 0);
            $groups[$key]['count']++;
            $groups[$key]['total_ms'] += $time;
            $groups[$key]['max_ms'] = max($groups[$key]['max_ms'], $time);
        }

        foreach ($groups as &$group) {
            $group['total_ms'] = round($group['total_ms'], 3);
            $group['max_ms'] = round($group['max_ms'], 3);
            $group['avg_ms'] = round($group['total_ms'] / max(1, $group['count']), 3);
        }

        usort($groups, fn (array $a, array $b): int => [$b['count'], $b['total_ms']] <=> [$a['count'], $a['total_ms']]);

        return array_values($groups);
    }

    private function buildTree(array $roots, array $childrenByParent): array
    {
        usort($roots, fn (array $a, array $b): int => ($b['duration_ms'] ?? 0) <=> ($a['duration_ms'] ?? 0));

        return array_map(fn (array $span): array => $this->treeNode($span, $childrenByParent), $roots);
    }

    private function treeNode(array $span, array $childrenByParent): array
    {
        $children = $childrenByParent[(string) ($span['id'] ?? '')] ?? [];
        usort($children, fn (array $a, array $b): int => ($b['duration_ms'] ?? 0) <=> ($a['duration_ms'] ?? 0));

        return [
            ...$this->spanRow($span),
            'children' => array_map(fn (array $child): array => $this->treeNode($child, $childrenByParent), $children),
        ];
    }

    private function spanRow(array $span): array
    {
        return [
            'name' => $span['name'] ?? 'unknown',
            'duration_ms' => round((float) ($span['duration_ms'] ?? 0), 3),
            'self_ms' => round((float) ($span['self_ms'] ?? 0), 3),
            'queries' => (int) ($span['query_count'] ?? 0),
            'query_time_ms' => round((float) ($span['query_time_ms'] ?? 0), 3),
            'memory_mb' => round((float) ($span['memory_mb'] ?? 0), 2),
            'line' => $span['_line'] ?? null,
            'context' => $this->importantContext($span['context'] ?? []),
        ];
    }

    private function renderRequestList(array $summaries, string $path, int $top): int
    {
        if ((bool) $this->option('json')) {
            $this->line(json_encode($summaries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Plan grid profile requests');
        $this->line('File: '.$path);
        $this->newLine();

        $this->table(
            ['Request', 'Last seen', 'Kind', 'Route', 'Lines', 'Spans', 'Query rows', 'Root ms', 'Root q', 'Peak MB'],
            array_map(fn (array $row): array => [
                $row['request_id'],
                $row['last_ts'],
                $row['livewire'] ? 'Livewire' : (string) ($row['method'] ?? ''),
                $row['path'],
                $row['lines'],
                $row['span_count'],
                $row['query_line_count'],
                $row['root_duration_ms'],
                $row['root_query_count'],
                $row['peak_memory_mb'],
            ], array_slice($summaries, 0, $top)),
        );

        return self::SUCCESS;
    }

    private function renderRequestAnalysis(array $analysis, string $path, int $top): void
    {
        $summary = $analysis['summary'];

        $this->info('Plan grid profile request '.$summary['request_id']);
        $this->line('File: '.$path);
        $this->line(sprintf(
            '%s %s | %s | lines %s-%s | spans %d | marks %d | query rows %d',
            $summary['method'] ?? '',
            $summary['path'] ?? '',
            $summary['livewire'] ? 'Livewire' : 'HTTP',
            $summary['line_start'],
            $summary['line_end'],
            $summary['span_count'],
            $summary['mark_count'],
            $summary['query_line_count'],
        ));
        $this->line(sprintf(
            'Root totals: %.1fms, %d queries, %.1fms DB time, peak %.1fMB',
            $summary['root_duration_ms'],
            $summary['root_query_count'],
            $summary['root_query_time_ms'],
            $summary['peak_memory_mb'],
        ));
        $this->newLine();

        $this->renderSpanTable('Top inclusive spans', $analysis['top_inclusive'], $top);
        $this->renderSpanTable('Top self-time spans', $analysis['top_self'], $top);
        $this->renderNameTable('Repeated span groups', $analysis['by_name'], $top);
    }

    private function renderSpanTable(string $title, array $rows, int $top): void
    {
        $this->line("<info>{$title}</info>");
        $this->table(
            ['Duration', 'Self', 'Queries', 'DB ms', 'Mem', 'Line', 'Span', 'Context'],
            array_map(fn (array $row): array => [
                $row['duration_ms'],
                $row['self_ms'],
                $row['queries'],
                $row['query_time_ms'],
                $row['memory_mb'],
                $row['line'],
                $this->shortName((string) $row['name']),
                $this->contextString($row['context']),
            ], array_slice($rows, 0, $top)),
        );
    }

    private function renderNameTable(string $title, array $rows, int $top): void
    {
        $this->line("<info>{$title}</info>");
        $this->table(
            ['Total', 'Self', 'Count', 'Avg', 'Max', 'Queries', 'DB ms', 'Span', 'Context samples'],
            array_map(fn (array $row): array => [
                $row['total_ms'],
                $row['self_ms'],
                $row['count'],
                $row['avg_ms'],
                $row['max_ms'],
                $row['queries'],
                $row['query_time_ms'],
                $this->shortName((string) $row['name']),
                implode(' | ', array_map(fn (array $context): string => $this->contextString($context), $row['contexts'])),
            ], array_slice($rows, 0, $top)),
        );
    }

    private function renderTree(array $analysis, float $minMs): void
    {
        $this->line('<info>Span tree</info>');

        foreach ($analysis['tree'] as $node) {
            $this->renderTreeNode($node, 0, $minMs);
        }

        $this->newLine();
    }

    private function renderTreeNode(array $node, int $depth, float $minMs): void
    {
        if ($node['duration_ms'] < $minMs) {
            return;
        }

        $this->line(sprintf(
            '%s%s %.1fms self %.1fms q=%d db=%.1fms %s',
            str_repeat('  ', $depth),
            $this->shortName((string) $node['name']),
            $node['duration_ms'],
            $node['self_ms'],
            $node['queries'],
            $node['query_time_ms'],
            $this->contextString($node['context']),
        ));

        foreach ($node['children'] as $child) {
            $this->renderTreeNode($child, $depth + 1, $minMs);
        }
    }

    private function renderQueryGroups(array $analysis, int $top): void
    {
        $groups = $analysis['query_groups'];

        if ($groups === []) {
            $this->warn('No query rows were logged for this request. Re-run with ?profile_plan_grid=queries.');

            return;
        }

        $this->line('<info>Grouped query fingerprints</info>');
        $this->table(
            ['Count', 'Total ms', 'Avg', 'Max', 'Parent span', 'SQL'],
            array_map(fn (array $row): array => [
                $row['count'],
                $row['total_ms'],
                $row['avg_ms'],
                $row['max_ms'],
                $this->shortName((string) $row['parent']),
                $this->truncate((string) $row['sql'], 140),
            ], array_slice($groups, 0, $top)),
        );
    }

    private function importantContext(array $context): array
    {
        $keys = [
            'component',
            'view',
            'plan_id',
            'scheduled_training_program_id',
            'program_exercise_id',
            'exercise_id',
            'user_id',
            'weeks',
            'sessions_per_week',
            'field',
            'week',
            'session',
            'set',
            'settings',
            'active_section',
            'result_count',
            'session_count_total',
            'rows',
            'week_columns',
        ];

        return array_filter(
            array_intersect_key($context, array_flip($keys)),
            fn (mixed $value): bool => $value !== null && $value !== [],
        );
    }

    private function contextString(array $context): string
    {
        if ($context === []) {
            return '';
        }

        $parts = [];

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $value = '['.implode(',', array_slice($value, 0, 5)).(count($value) > 5 ? ',...' : '').']';
            }

            if ($key === 'component') {
                $value = class_basename((string) $value);
            }

            $parts[] = $key.'='.$value;
        }

        return $this->truncate(implode(' ', $parts), 120);
    }

    private function fingerprintSql(string $sql): string
    {
        $sql = preg_replace("/'(?:''|[^'])*'/", '?', $sql) ?? $sql;
        $sql = preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $sql) ?? $sql;
        $sql = preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;

        return strtolower($sql);
    }

    private function shortName(string $name): string
    {
        return str_replace([
            'PlanExerciseGrid.',
            'WithCalendarPlan.',
            'ExercisePreviewBuilder.',
            'StrategyOrchestrator.',
            'ResolvedPlannedSessionBuilder.',
        ], [
            'Grid.',
            'CalendarPlan.',
            'Preview.',
            'Strategy.',
            'Resolved.',
        ], $name);
    }

    private function truncate(string $value, int $length): string
    {
        return strlen($value) > $length ? substr($value, 0, $length - 3).'...' : $value;
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}
