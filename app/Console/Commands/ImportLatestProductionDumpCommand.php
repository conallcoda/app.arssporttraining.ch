<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ImportLatestProductionDumpCommand extends Command
{
    public const SIGNATURE = 'db:import-latest-production-dump
        {dump? : Optional dump filename inside import/dumps or an absolute dump path}';

    protected $signature = self::SIGNATURE;

    protected $description = 'Wipes the local database and imports the latest production SQL dump from import/dumps';

    public function handle(): int
    {
        $connection = $this->databaseConnection();
        $dumpPath = $this->resolveDumpPath();
        $mysqlBinary = $this->mysqlBinary();
        $gzipBinary = $this->gzipBinary();

        DB::disconnect(config('database.default'));
        DB::purge(config('database.default'));

        $this->info('Importing production dump into local database.');
        $this->line('Database: '.$connection['database']);
        $this->line('Dump: '.$dumpPath);

        $this->recreateDatabase($mysqlBinary, $connection);
        $this->importDump($mysqlBinary, $gzipBinary, $connection, $dumpPath);

        $this->info('Production dump imported successfully.');

        return self::SUCCESS;
    }

    /**
     * @return array{
     *     driver: string,
     *     host: string,
     *     port: string,
     *     database: string,
     *     username: string,
     *     password: string,
     *     charset: string,
     *     collation: string
     * }
     */
    private function databaseConnection(): array
    {
        $defaultConnection = (string) config('database.default');
        $connection = config('database.connections.'.$defaultConnection);

        if (! is_array($connection)) {
            $this->fail("Database connection [{$defaultConnection}] is not configured.");
        }

        $driver = (string) ($connection['driver'] ?? '');

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->fail("Database connection [{$defaultConnection}] must use mysql or mariadb.");
        }

        $database = (string) ($connection['database'] ?? '');

        if ($database === '') {
            $this->fail("Database connection [{$defaultConnection}] has no database name configured.");
        }

        return [
            'driver' => $driver,
            'host' => (string) ($connection['host'] ?? '127.0.0.1'),
            'port' => (string) ($connection['port'] ?? '3306'),
            'database' => $database,
            'username' => (string) ($connection['username'] ?? ''),
            'password' => (string) ($connection['password'] ?? ''),
            'charset' => (string) ($connection['charset'] ?? 'utf8mb4'),
            'collation' => (string) ($connection['collation'] ?? 'utf8mb4_unicode_ci'),
        ];
    }

    private function resolveDumpPath(): string
    {
        $dump = $this->argument('dump');

        if (is_string($dump) && $dump !== '') {
            $path = str_starts_with($dump, DIRECTORY_SEPARATOR)
                ? $dump
                : base_path('import/dumps/'.$dump);

            if (! File::exists($path)) {
                $this->fail("Dump file not found: {$path}");
            }

            return $path;
        }

        $dumps = collect(File::files(base_path('import/dumps')))
            ->filter(fn (\SplFileInfo $file): bool => in_array($file->getExtension(), ['sql', 'gz'], true))
            ->sortBy(fn (\SplFileInfo $file): string => $file->getFilename())
            ->values();

        if ($dumps->isEmpty()) {
            $this->fail('No dump files found in import/dumps.');
        }

        return $dumps->last()->getPathname();
    }

    private function mysqlBinary(): string
    {
        $preferredBinary = '/Users/Shared/DBngin/mariadb/10.11.6_arm/bin/mysql';

        if (File::exists($preferredBinary)) {
            return $preferredBinary;
        }

        $binary = (new ExecutableFinder)->find('mysql');

        if ($binary === false) {
            $this->fail('mysql binary not found.');
        }

        return $binary;
    }

    private function gzipBinary(): string
    {
        $binary = (new ExecutableFinder)->find('gzip');

        if ($binary === false) {
            $this->fail('gzip binary not found.');
        }

        return $binary;
    }

    /**
     * @param  array{
     *     host: string,
     *     port: string,
     *     database: string,
     *     username: string,
     *     password: string,
     *     charset: string,
     *     collation: string
     * }  $connection
     */
    private function recreateDatabase(string $mysqlBinary, array $connection): void
    {
        $database = $this->quoteIdentifier($connection['database']);
        $charset = $connection['charset'];
        $collation = $connection['collation'];
        $sql = "DROP DATABASE IF EXISTS {$database}; CREATE DATABASE {$database} CHARACTER SET {$charset} COLLATE {$collation};";

        $this->line('Recreating local database...');

        $process = new Process([
            $mysqlBinary,
            '--protocol=TCP',
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--user='.$connection['username'],
            '-e',
            $sql,
        ]);

        $process->setTimeout(null);
        $process->setEnv(['MYSQL_PWD' => $connection['password']]);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->fail('Failed to recreate database: '.$process->getErrorOutput());
        }
    }

    /**
     * @param  array{
     *     host: string,
     *     port: string,
     *     database: string,
     *     username: string,
     *     password: string
     * }  $connection
     */
    private function importDump(string $mysqlBinary, string $gzipBinary, array $connection, string $dumpPath): void
    {
        $this->line('Importing dump into local database...');

        $sanitizeCommand = "awk 'NR == 1 && /enable the sandbox mode/ { next } { print }'";

        $importCommand = str_ends_with($dumpPath, '.gz')
            ? sprintf(
                '%s -dc %s | %s | %s --protocol=TCP --host=%s --port=%s --user=%s %s',
                escapeshellarg($gzipBinary),
                escapeshellarg($dumpPath),
                $sanitizeCommand,
                escapeshellarg($mysqlBinary),
                escapeshellarg($connection['host']),
                escapeshellarg($connection['port']),
                escapeshellarg($connection['username']),
                escapeshellarg($connection['database']),
            )
            : sprintf(
                '%s < %s | %s --protocol=TCP --host=%s --port=%s --user=%s %s',
                $sanitizeCommand,
                escapeshellarg($dumpPath),
                escapeshellarg($mysqlBinary),
                escapeshellarg($connection['host']),
                escapeshellarg($connection['port']),
                escapeshellarg($connection['username']),
                escapeshellarg($connection['database']),
            );

        $process = Process::fromShellCommandline($importCommand);
        $process->setTimeout(null);
        $process->setEnv(['MYSQL_PWD' => $connection['password']]);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->fail('Failed to import dump: '.$process->getErrorOutput());
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
}
