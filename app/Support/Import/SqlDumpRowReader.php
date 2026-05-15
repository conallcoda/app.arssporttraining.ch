<?php

namespace App\Support\Import;

use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SqlDumpRowReader
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function readRows(string $dumpPath, string $table): array
    {
        $columns = Schema::getColumnListing($table);

        if ($columns === []) {
            throw new RuntimeException("Unknown table [{$table}] for dump parsing.");
        }

        $handle = $this->openDumpStream($dumpPath);
        $rows = [];
        $inSection = false;
        $header = "INSERT INTO `{$table}` VALUES";

        try {
            while (($line = $this->readDumpLine($handle, $dumpPath)) !== null) {
                $trimmed = trim($line);

                if (! $inSection) {
                    if ($trimmed === $header) {
                        $inSection = true;
                    }

                    continue;
                }

                if ($trimmed === '' || str_starts_with($trimmed, '/*!40000 ALTER TABLE')) {
                    break;
                }

                if (! str_starts_with($trimmed, '(')) {
                    continue;
                }

                $rows[] = $this->parseRow($trimmed, $columns, $table);
            }
        } finally {
            $this->closeDumpStream($handle, $dumpPath);
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<string, mixed>
     */
    private function parseRow(string $line, array $columns, string $table): array
    {
        $trimmed = rtrim($line, ",;\r\n\t ");
        $payload = substr($trimmed, 1, -1);
        $values = str_getcsv($payload, ',', "'", '\\');

        if ($values === false || count($values) !== count($columns)) {
            throw new RuntimeException(sprintf(
                'Unable to parse row for table [%s]. Expected %d columns, got %d. Row: %s',
                $table,
                count($columns),
                is_array($values) ? count($values) : 0,
                substr($line, 0, 220),
            ));
        }

        $normalized = array_map(
            fn (string $value): mixed => $value === 'NULL' ? null : stripcslashes($value),
            $values,
        );

        /** @var array<string, mixed> $row */
        $row = array_combine($columns, $normalized);

        return $row;
    }

    /**
     * @return resource
     */
    private function openDumpStream(string $dumpPath)
    {
        $handle = str_ends_with($dumpPath, '.gz')
            ? gzopen($dumpPath, 'rb')
            : fopen($dumpPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open dump file [{$dumpPath}].");
        }

        return $handle;
    }

    /**
     * @param  resource  $handle
     */
    private function readDumpLine($handle, string $dumpPath): ?string
    {
        $line = str_ends_with($dumpPath, '.gz') ? gzgets($handle) : fgets($handle);

        return $line === false ? null : $line;
    }

    /**
     * @param  resource  $handle
     */
    private function closeDumpStream($handle, string $dumpPath): void
    {
        if (str_ends_with($dumpPath, '.gz')) {
            gzclose($handle);

            return;
        }

        fclose($handle);
    }
}
