<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use SplFileObject;

class SummarizeLogErrorsCommand extends Command
{
    protected $signature = 'logs:errors
        {--path= : Log file path. Defaults to ./laravel-prod.log}
        {--level=ERROR : Log level to include. Use "all" to include every level}
        {--limit=0 : Maximum number of rows to show. Use 0 for all}
        {--full : Do not truncate long messages in the table}';

    protected $description = 'Summarize unique Laravel log error messages with counts and first/last seen timestamps.';

    public function handle(): int
    {
        $path = $this->resolveLogPath($this->option('path') ?: 'laravel-prod.log');
        $level = strtoupper((string) $this->option('level'));
        $limit = max(0, (int) $this->option('limit'));
        $truncate = ! (bool) $this->option('full');

        if (! is_file($path)) {
            $this->error("Log file not found: {$path}");

            return self::FAILURE;
        }

        $summary = $this->summarize($path, $level);

        if ($summary === []) {
            $levelText = $level === 'ALL' ? 'log entries' : $level.' entries';
            $this->info("No {$levelText} found in {$path}.");

            return self::SUCCESS;
        }

        usort($summary, fn (array $a, array $b): int => strcmp($b['last_seen'], $a['last_seen']));

        $totalUniqueMessages = count($summary);

        if ($limit > 0) {
            $summary = array_slice($summary, 0, $limit);
        }

        $this->info('Unique log messages: '.$totalUniqueMessages);

        if ($limit > 0 && count($summary) < $totalUniqueMessages) {
            $this->line('Showing: '.count($summary));
        }

        $this->line('File: '.$path);
        $this->newLine();

        $this->table(
            ['Count', 'First seen', 'Last seen', 'Level', 'Message'],
            array_map(fn (array $row): array => [
                $row['count'],
                $row['first_seen'],
                $row['last_seen'],
                implode(', ', $row['levels']),
                $truncate ? $this->truncateMessage($row['message']) : $row['message'],
            ], $summary),
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{message: string, count: int, first_seen: string, last_seen: string, levels: array<int, string>}>
     */
    private function summarize(string $path, string $level): array
    {
        $entries = [];
        $file = new SplFileObject($path);

        while (! $file->eof()) {
            $line = rtrim((string) $file->fgets(), "\r\n");
            $entry = $this->parseLogEntryStart($line);

            if ($entry === null) {
                continue;
            }

            if ($level !== 'ALL' && $entry['level'] !== $level) {
                continue;
            }

            $message = $entry['message'];

            if (! isset($entries[$message])) {
                $entries[$message] = [
                    'message' => $message,
                    'count' => 0,
                    'first_seen' => $entry['timestamp'],
                    'last_seen' => $entry['timestamp'],
                    'levels' => [],
                ];
            }

            $entries[$message]['count']++;
            $entries[$message]['first_seen'] = min($entries[$message]['first_seen'], $entry['timestamp']);
            $entries[$message]['last_seen'] = max($entries[$message]['last_seen'], $entry['timestamp']);
            $entries[$message]['levels'][$entry['level']] = $entry['level'];
        }

        return array_values(array_map(function (array $entry): array {
            $entry['levels'] = array_values($entry['levels']);
            sort($entry['levels']);

            return $entry;
        }, $entries));
    }

    /**
     * @return array{timestamp: string, level: string, message: string}|null
     */
    private function parseLogEntryStart(string $line): ?array
    {
        if (! preg_match('/^\[(?<timestamp>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+[^.]+\.(?<level>[A-Z]+):\s*(?<message>.*)$/', $line, $matches)) {
            return null;
        }

        return [
            'timestamp' => $matches['timestamp'],
            'level' => $matches['level'],
            'message' => $this->stripTrailingContext($matches['message']),
        ];
    }

    private function stripTrailingContext(string $message): string
    {
        if (preg_match('/\s+\{"[A-Za-z0-9_-]+":/', $message, $matches, PREG_OFFSET_CAPTURE)) {
            return rtrim(substr($message, 0, $matches[0][1]));
        }

        $offset = strlen($message);

        while (($position = strrpos(substr($message, 0, $offset), ' {')) !== false) {
            $context = substr($message, $position + 1);

            if (json_decode($context, true) !== null && json_last_error() === JSON_ERROR_NONE) {
                return rtrim(substr($message, 0, $position));
            }

            $offset = $position;
        }

        return trim($message);
    }

    private function truncateMessage(string $message): string
    {
        return strlen($message) > 180 ? substr($message, 0, 177).'...' : $message;
    }

    private function resolveLogPath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}
