<?php

namespace App\Console\Commands;

use PDO;
use PDOException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class LiveImportDatabaseCommand extends Command
{
    protected $signature = 'db:live-import
        {dump? : Optional path to a local .sql or .sql.gz dump}
        {--skip-pull : Reuse the newest dump in import/dumps instead of downloading a fresh one (default)}
        {--pull : Download a fresh dump before importing}';

    protected $description = 'Download the production SQL dump and import it into the local development database';

    public function handle(): int
    {
        $dumpPath = $this->resolveDumpPath();

        if ($dumpPath === null) {
            return self::FAILURE;
        }

        if (! $this->wipeLocalDatabase()) {
            return self::FAILURE;
        }

        if (! $this->importDump($dumpPath)) {
            return self::FAILURE;
        }

        if ($this->call(FixDataMay2026Command::SIGNATURE) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->call('optimize:clear');

        $this->newLine();
        $this->info("Local database refreshed from {$this->relativePath($dumpPath)}");

        return self::SUCCESS;
    }

    private function resolveDumpPath(): ?string
    {
        $dumpArgument = $this->argument('dump');

        if (is_string($dumpArgument) && $dumpArgument !== '') {
            $dumpPath = $this->normalizeDumpPath($dumpArgument);

            if (! is_file($dumpPath)) {
                $this->error("Dump file not found: {$dumpPath}");

                return null;
            }

            return $dumpPath;
        }

        if (! $this->shouldPullFreshDump()) {
            $dumpPath = $this->latestLocalDumpPath();

            if ($dumpPath === null) {
                $this->error('No local dump found in import/dumps.');
            }

            return $dumpPath;
        }

        if (! $this->downloadFreshDump()) {
            return null;
        }

        $dumpPath = $this->latestLocalDumpPath();

        if ($dumpPath === null) {
            $this->error('Download completed but no dump was found in import/dumps.');
        }

        return $dumpPath;
    }

    private function shouldPullFreshDump(): bool
    {
        return (bool) $this->option('pull');
    }

    private function normalizeDumpPath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }

    private function downloadFreshDump(): bool
    {
        $scriptPath = base_path('scripts/pull-production-db.sh');

        if (! is_file($scriptPath)) {
            $this->error('Missing dump pull script: scripts/pull-production-db.sh');

            return false;
        }

        $this->info('Downloading fresh production dump...');

        $process = new Process([$scriptPath], base_path());
        $process->setTimeout(600);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error('Production dump download failed.');

            return false;
        }

        return true;
    }

    private function latestLocalDumpPath(): ?string
    {
        $paths = File::glob(base_path('import/dumps/*.sql')) ?: [];
        $gzPaths = File::glob(base_path('import/dumps/*.sql.gz')) ?: [];
        $dumps = array_merge($paths, $gzPaths);

        if ($dumps === []) {
            return null;
        }

        usort($dumps, fn (string $left, string $right) => filemtime($right) <=> filemtime($left));

        return $dumps[0];
    }

    private function wipeLocalDatabase(): bool
    {
        $this->info('Wiping local database...');

        return $this->call('db:wipe', ['--force' => true]) === self::SUCCESS;
    }

    private function importDump(string $dumpPath): bool
    {
        $databaseConfig = $this->databaseConfig();

        if ($databaseConfig === null) {
            return false;
        }

        $this->info('Importing dump into local database...');

        $mysqlCommand = $this->mysqlCommand($databaseConfig);

        if ($mysqlCommand !== null) {
            return $this->importDumpViaMysqlClient($dumpPath, $mysqlCommand, $databaseConfig);
        }

        $this->line('`mysql` client not found, using PHP/PDO importer instead.');

        return $this->importDumpViaPdo($dumpPath, $databaseConfig);
    }

    /** @param array<string, mixed> $databaseConfig */
    private function importDumpViaMysqlClient(string $dumpPath, string $mysqlCommand, array $databaseConfig): bool
    {
        $dumpArgument = escapeshellarg($dumpPath);

        $shellCommand = str_ends_with($dumpPath, '.gz')
            ? "gzip -dc {$dumpArgument} | {$mysqlCommand}"
            : "{$mysqlCommand} < {$dumpArgument}";

        $environment = [];

        if (($databaseConfig['password'] ?? '') !== '') {
            $environment['MYSQL_PWD'] = $databaseConfig['password'];
        }

        $process = Process::fromShellCommandline($shellCommand, base_path(), $environment);
        $process->setTimeout(600);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error('Local database import failed.');

            return false;
        }

        return true;
    }

    /** @param array<string, mixed> $databaseConfig */
    private function importDumpViaPdo(string $dumpPath, array $databaseConfig): bool
    {
        try {
            $pdo = $this->makePdo($databaseConfig);
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $pdo->exec('SET SQL_MODE=""');
        } catch (PDOException $e) {
            $this->error('Failed to connect to local database: '.$e->getMessage());

            return false;
        }

        $handle = $this->openDumpStream($dumpPath);

        if ($handle === null) {
            return false;
        }

        $delimiter = ';';
        $statement = '';
        $lineNumber = 0;

        try {
            while (($line = $this->readDumpLine($handle, $dumpPath)) !== null) {
                $lineNumber++;
                $trimmed = trim($line);

                if ($statement === '' && $this->shouldSkipLine($trimmed)) {
                    continue;
                }

                if ($statement === '' && str_starts_with($trimmed, 'DELIMITER ')) {
                    $delimiter = substr($trimmed, 10) ?: ';';

                    continue;
                }

                $statement .= $line;

                if (! $this->statementIsComplete($statement, $delimiter)) {
                    continue;
                }

                $sql = $this->finalizeStatement($statement, $delimiter);
                $statement = '';

                if ($sql === '') {
                    continue;
                }

                $pdo->exec($sql);
            }

            if (trim($statement) !== '') {
                $pdo->exec($statement);
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } catch (PDOException $e) {
            $this->closeDumpStream($handle, $dumpPath);
            $preview = $this->previewStatement($statement);
            $this->error("Local database import failed near line {$lineNumber}: ".$e->getMessage());

            if ($preview !== '') {
                $this->line($preview);
            }

            return false;
        }

        $this->closeDumpStream($handle, $dumpPath);

        return true;
    }

    /** @return array<string, mixed>|null */
    private function databaseConfig(): ?array
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (! is_array($config)) {
            $this->error("Unsupported database connection [{$connection}].");

            return null;
        }

        $driver = $config['driver'] ?? null;

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->error("db:live-import only supports MySQL/MariaDB local connections, current driver: {$driver}.");

            return null;
        }

        return $config;
    }

    /** @param array<string, mixed> $databaseConfig */
    private function mysqlCommand(array $databaseConfig): ?string
    {
        $binary = $this->findMysqlBinary();

        if ($binary === null) {
            return null;
        }

        $parts = [$binary];

        if (($databaseConfig['unix_socket'] ?? '') !== '') {
            $parts[] = '--socket='.escapeshellarg((string) $databaseConfig['unix_socket']);
        } else {
            $parts[] = '--host='.escapeshellarg((string) ($databaseConfig['host'] ?? '127.0.0.1'));
            $parts[] = '--port='.escapeshellarg((string) ($databaseConfig['port'] ?? '3306'));
        }

        $parts[] = '--user='.escapeshellarg((string) ($databaseConfig['username'] ?? 'root'));

        if (($databaseConfig['charset'] ?? '') !== '') {
            $parts[] = '--default-character-set='.escapeshellarg((string) $databaseConfig['charset']);
        }

        $parts[] = escapeshellarg((string) $databaseConfig['database']);

        return implode(' ', $parts);
    }

    private function findMysqlBinary(): ?string
    {
        foreach (['mysql', 'mariadb'] as $candidate) {
            $process = new Process(['which', $candidate]);
            $process->run();

            if ($process->isSuccessful()) {
                $path = trim($process->getOutput());

                if ($path !== '') {
                    return $path;
                }
            }
        }

        return null;
    }

    /** @param array<string, mixed> $databaseConfig */
    private function makePdo(array $databaseConfig): PDO
    {
        $charset = (string) ($databaseConfig['charset'] ?? 'utf8mb4');
        $database = (string) $databaseConfig['database'];

        if (($databaseConfig['unix_socket'] ?? '') !== '') {
            $dsn = sprintf(
                'mysql:unix_socket=%s;dbname=%s;charset=%s',
                $databaseConfig['unix_socket'],
                $database,
                $charset,
            );
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $databaseConfig['host'] ?? '127.0.0.1',
                $databaseConfig['port'] ?? '3306',
                $database,
                $charset,
            );
        }

        return new PDO(
            $dsn,
            (string) ($databaseConfig['username'] ?? 'root'),
            (string) ($databaseConfig['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            ],
        );
    }

    /** @return resource|null */
    private function openDumpStream(string $dumpPath)
    {
        if (str_ends_with($dumpPath, '.gz')) {
            $handle = gzopen($dumpPath, 'rb');
        } else {
            $handle = fopen($dumpPath, 'rb');
        }

        if ($handle === false) {
            $this->error("Unable to open dump file: {$dumpPath}");

            return null;
        }

        return $handle;
    }

    /** @param resource $handle */
    private function readDumpLine($handle, string $dumpPath): ?string
    {
        $line = str_ends_with($dumpPath, '.gz') ? gzgets($handle) : fgets($handle);

        if ($line === false) {
            return null;
        }

        return $line;
    }

    /** @param resource $handle */
    private function closeDumpStream($handle, string $dumpPath): void
    {
        if (str_ends_with($dumpPath, '.gz')) {
            gzclose($handle);

            return;
        }

        fclose($handle);
    }

    private function shouldSkipLine(string $trimmed): bool
    {
        return $trimmed === ''
            || str_starts_with($trimmed, '-- ')
            || str_starts_with($trimmed, '/*!')
            || str_starts_with($trimmed, '/*!')
            || $trimmed === '/*'
            || $trimmed === '*/';
    }

    private function statementIsComplete(string $statement, string $delimiter): bool
    {
        return str_ends_with(rtrim($statement), $delimiter);
    }

    private function finalizeStatement(string $statement, string $delimiter): string
    {
        $trimmed = rtrim($statement);

        if (str_ends_with($trimmed, $delimiter)) {
            $trimmed = substr($trimmed, 0, -strlen($delimiter));
        }

        return trim($trimmed);
    }

    private function previewStatement(string $statement): string
    {
        $sql = preg_replace('/\s+/', ' ', trim($statement)) ?? '';

        if ($sql === '') {
            return '';
        }

        return 'Statement preview: '.substr($sql, 0, 220);
    }

    private function relativePath(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), DIRECTORY_SEPARATOR);
    }
}
