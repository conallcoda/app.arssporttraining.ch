#!/usr/bin/env php
<?php

declare(strict_types=1);

const DEFAULT_CODA_WORKSPACE = '/Users/conalloreilly/Development/coda-packages';
const DEFAULT_CONSUMERS = [
    '/Users/conalloreilly/Development/cfc/cfc-app/admin',
    '/Users/conalloreilly/Development/ars/athlete-training',
];

final class CommandFailed extends RuntimeException {}

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, "Error: {$message}\n");
    exit($code);
}

function run(array $command, string $cwd, bool $capture = false): string
{
    $descriptorSpec = [
        0 => STDIN,
        1 => $capture ? ['pipe', 'w'] : STDOUT,
        2 => $capture ? ['pipe', 'w'] : STDERR,
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, $cwd);
    if (! is_resource($process)) {
        throw new CommandFailed('Unable to start: '.implode(' ', $command));
    }

    $stdout = $capture ? stream_get_contents($pipes[1]) : '';
    $stderr = $capture ? stream_get_contents($pipes[2]) : '';
    if ($capture) {
        fclose($pipes[1]);
        fclose($pipes[2]);
    }

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        $detail = trim($stderr ?: $stdout);
        throw new CommandFailed(
            sprintf('%s failed in %s%s', implode(' ', $command), $cwd, $detail === '' ? '' : ":\n{$detail}"),
            $exitCode,
        );
    }

    return trim($stdout);
}

function git(string $cwd, array $arguments, bool $capture = false): string
{
    return run(['git', ...$arguments], $cwd, $capture);
}

function composer(string $cwd, array $arguments): void
{
    run(['composer', ...$arguments], $cwd);
}

function codaWorkspace(): string
{
    return rtrim(getenv('CODA_PACKAGES_PATH') ?: DEFAULT_CODA_WORKSPACE, DIRECTORY_SEPARATOR);
}

/** @return list<string> */
function consumerPaths(): array
{
    $configured = getenv('CODA_CONSUMERS');
    if ($configured === false || trim($configured) === '') {
        return DEFAULT_CONSUMERS;
    }

    return array_values(array_filter(explode(PATH_SEPARATOR, $configured)));
}

function composerRoot(string $start): string
{
    $directory = realpath($start) ?: $start;
    while ($directory !== dirname($directory)) {
        if (is_file($directory.'/composer.json')) {
            return $directory;
        }
        $directory = dirname($directory);
    }

    fail('Run this command from a Composer project.');
}

function gitRoot(string $path): string
{
    return git($path, ['rev-parse', '--show-toplevel'], true);
}

/** @return array<string, array{name: string, path: string, composer_name: string}> */
function packages(): array
{
    $workspace = codaWorkspace();
    if (! is_dir($workspace)) {
        fail("Coda workspace does not exist: {$workspace}");
    }

    $packages = [];
    foreach (glob($workspace.'/*/composer.json') ?: [] as $manifest) {
        $data = json_decode((string) file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
        $composerName = $data['name'] ?? null;
        if (! is_string($composerName) || ! str_starts_with($composerName, 'coda/')) {
            continue;
        }

        $path = dirname($manifest);
        $name = basename($path);
        $packages[$name] = ['name' => $name, 'path' => $path, 'composer_name' => $composerName];
    }

    ksort($packages);
    return $packages;
}

/** @return array<string, true> */
function lockedCodaPackages(string $project): array
{
    $lockFile = $project.'/composer.lock';
    if (! is_file($lockFile)) {
        return [];
    }

    $lock = json_decode((string) file_get_contents($lockFile), true, flags: JSON_THROW_ON_ERROR);
    $locked = [];
    foreach ([...($lock['packages'] ?? []), ...($lock['packages-dev'] ?? [])] as $package) {
        $name = $package['name'] ?? null;
        if (is_string($name) && str_starts_with($name, 'coda/')) {
            $locked[$name] = true;
        }
    }

    return $locked;
}

function relativeLinkTarget(string $source, string $destinationDirectory): string
{
    $sourceParts = explode(DIRECTORY_SEPARATOR, trim($source, DIRECTORY_SEPARATOR));
    $destinationParts = explode(DIRECTORY_SEPARATOR, trim($destinationDirectory, DIRECTORY_SEPARATOR));

    while ($sourceParts !== [] && $destinationParts !== [] && $sourceParts[0] === $destinationParts[0]) {
        array_shift($sourceParts);
        array_shift($destinationParts);
    }

    return str_repeat('..'.DIRECTORY_SEPARATOR, count($destinationParts)).implode(DIRECTORY_SEPARATOR, $sourceParts);
}

function removeDirectory(string $path): void
{
    if (is_link($path) || is_file($path)) {
        if (! unlink($path)) {
            throw new RuntimeException("Unable to remove {$path}");
        }
        return;
    }

    if (! is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function linkPackages(string $project): void
{
    $vendor = $project.'/vendor/coda';
    $backups = $project.'/vendor/.coda-original';
    if (! is_dir($vendor) && ! mkdir($vendor, 0777, true) && ! is_dir($vendor)) {
        throw new RuntimeException("Unable to create {$vendor}");
    }
    if (! is_dir($backups) && ! mkdir($backups, 0777, true) && ! is_dir($backups)) {
        throw new RuntimeException("Unable to create {$backups}");
    }

    $locked = lockedCodaPackages($project);
    foreach (packages() as $package) {
        if (! isset($locked[$package['composer_name']])) {
            continue;
        }
        $target = $vendor.'/'.$package['name'];
        $backup = $backups.'/'.$package['name'];

        if (is_link($target)) {
            $resolved = realpath($target);
            if ($resolved === realpath($package['path'])) {
                printf("linked    %-20s %s\n", $package['composer_name'], $package['path']);
                continue;
            }
            unlink($target);
        }

        if (file_exists($target)) {
            if (file_exists($backup) || is_link($backup)) {
                removeDirectory($backup);
            }
            if (! rename($target, $backup)) {
                throw new RuntimeException("Unable to preserve {$target}");
            }
        } elseif (! is_dir($project.'/vendor/composer')) {
            continue;
        }

        $relativeSource = relativeLinkTarget($package['path'], dirname($target));
        if (! symlink($relativeSource, $target)) {
            throw new RuntimeException("Unable to link {$target}");
        }
        printf("linked    %-20s %s\n", $package['composer_name'], $package['path']);
    }
}

function unlinkPackages(string $project, bool $installMissing = true): void
{
    $vendor = $project.'/vendor/coda';
    $backups = $project.'/vendor/.coda-original';

    $missing = false;
    foreach (packages() as $package) {
        $target = $vendor.'/'.$package['name'];
        $backup = $backups.'/'.$package['name'];

        if (is_link($target)) {
            unlink($target);
            $missing = $missing || ! file_exists($backup);
        }
        if (file_exists($backup) && ! file_exists($target)) {
            if (! rename($backup, $target)) {
                throw new RuntimeException("Unable to restore {$target}");
            }
            printf("restored  %s\n", $package['composer_name']);
        }
    }

    if (is_dir($backups) && count(scandir($backups) ?: []) === 2) {
        rmdir($backups);
    }

    if ($missing && $installMissing) {
        composer($project, ['install']);
    }
}

function status(string $project): void
{
    printf("Project:   %s\n", $project);
    printf("Workspace: %s\n\n", codaWorkspace());

    foreach (packages() as $package) {
        $gitState = 'not a Git repository';
        if (is_dir($package['path'].'/.git')) {
            $branch = git($package['path'], ['branch', '--show-current'], true) ?: 'detached';
            $changes = git($package['path'], ['status', '--porcelain'], true);
            $gitState = $branch.($changes === '' ? ', clean' : ', modified');
        }

        $target = $project.'/vendor/coda/'.$package['name'];
        $linkState = is_link($target) && realpath($target) === realpath($package['path'])
            ? 'linked'
            : (file_exists($target) ? 'installed' : 'not installed');

        printf("%-22s %-13s %s\n", $package['composer_name'], $linkState, $gitState);
    }
}

function confirm(string $prompt): bool
{
    if (! stream_isatty(STDIN)) {
        return false;
    }
    fwrite(STDOUT, $prompt.' [y/N] ');
    return in_array(strtolower(trim((string) fgets(STDIN))), ['y', 'yes'], true);
}

function assertPackageRepositories(): void
{
    foreach (packages() as $package) {
        if (! is_dir($package['path'].'/.git')) {
            throw new RuntimeException("{$package['composer_name']} is not a Git repository");
        }
        $branch = git($package['path'], ['branch', '--show-current'], true);
        if ($branch !== 'main') {
            throw new RuntimeException("{$package['composer_name']} must be on main; found {$branch}");
        }
        git($package['path'], ['remote', 'get-url', 'origin'], true);
    }
}

/** @return list<array{name: string, path: string, composer_name: string}> */
function dirtyPackages(): array
{
    return array_values(array_filter(
        packages(),
        fn (array $package): bool => git($package['path'], ['status', '--porcelain'], true) !== '',
    ));
}

function runConsumerChecks(): void
{
    foreach (consumerPaths() as $consumer) {
        if (! is_file($consumer.'/artisan')) {
            fwrite(STDOUT, "Skipping unavailable consumer: {$consumer}\n");
            continue;
        }
        fwrite(STDOUT, "\nTesting {$consumer}\n");
        run(['php', 'artisan', 'test'], $consumer);
        if (is_file($consumer.'/package.json')) {
            run(['npm', 'run', 'build'], $consumer);
        }
    }
}

function deploy(string $project, array $arguments): void
{
    $dryRun = in_array('--dry-run', $arguments, true);
    $skipChecks = in_array('--skip-checks', $arguments, true);
    $yes = in_array('--yes', $arguments, true);
    $message = null;
    foreach ($arguments as $index => $argument) {
        if (str_starts_with($argument, '--message=')) {
            $message = substr($argument, strlen('--message='));
        } elseif ($argument === '--message' || $argument === '-m') {
            $message = $arguments[$index + 1] ?? null;
        }
    }
    if (! is_string($message) || trim($message) === '') {
        fail('Deploy requires --message="...".');
    }

    assertPackageRepositories();
    $dirty = dirtyPackages();
    $appGitRoot = gitRoot($project);
    $appBranch = git($appGitRoot, ['branch', '--show-current'], true);
    git($appGitRoot, ['remote', 'get-url', 'origin'], true);

    fwrite(STDOUT, "Deployment plan\n");
    fwrite(STDOUT, "  application: {$appGitRoot} ({$appBranch})\n");
    fwrite(STDOUT, "  package commits: ".count($dirty)."\n");
    foreach ($dirty as $package) {
        fwrite(STDOUT, "    - {$package['composer_name']}\n");
        $summary = git($package['path'], ['status', '--short'], true);
        fwrite(STDOUT, preg_replace('/^/m', '      ', $summary)."\n");
    }

    if ($dryRun) {
        fwrite(STDOUT, "Dry run complete; nothing changed.\n");
        return;
    }
    if (! $yes && ! confirm('Commit and push these packages, then deploy the application?')) {
        fail('Deployment cancelled.');
    }

    composer($project, [
        'update',
        'coda/*',
        '--with-all-dependencies',
        '--minimal-changes',
        '--dry-run',
    ]);

    if (! $skipChecks) {
        runConsumerChecks();
    }

    foreach ($dirty as $package) {
        git($package['path'], ['add', '--all']);
        git($package['path'], ['commit', '-m', $message]);
        git($package['path'], ['push', '--set-upstream', 'origin', 'main']);
    }

    unlinkPackages($project, false);
    try {
        composer($project, [
            'update',
            'coda/*',
            '--with-all-dependencies',
            '--minimal-changes',
        ]);
        if (! $skipChecks) {
            run(['php', 'artisan', 'test'], $project);
            if (is_file($project.'/package.json')) {
                run(['npm', 'run', 'build'], $project);
            }
        }

        git($appGitRoot, ['add', '--all']);
        if (git($appGitRoot, ['status', '--porcelain'], true) !== '') {
            git($appGitRoot, ['commit', '-m', $message]);
        }
        git($appGitRoot, ['push', '--set-upstream', 'origin', $appBranch]);
    } finally {
        linkPackages($project);
    }
}

set_time_limit(0);
$project = composerRoot(getcwd());
$arguments = array_slice($argv, 1);
$command = array_shift($arguments) ?: 'status';

try {
    match ($command) {
        'link' => linkPackages($project),
        'unlink' => unlinkPackages($project),
        'status' => status($project),
        'deploy' => deploy($project, $arguments),
        default => fail("Unknown command '{$command}'. Use link, unlink, status, or deploy."),
    };
} catch (Throwable $exception) {
    fail($exception->getMessage(), $exception->getCode() > 0 ? $exception->getCode() : 1);
}
