<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        $this->guardAgainstUnsafeDatabaseConfiguration($app);

        return $app;
    }

    protected function guardAgainstUnsafeDatabaseConfiguration($app): void
    {
        $environment = $app['config']->get('app.env');
        $defaultConnection = $app['config']->get('database.default');
        $databaseName = (string) $app['config']->get("database.connections.{$defaultConnection}.database", '');

        if ($environment !== 'testing') {
            throw new RuntimeException("Refusing to run tests outside the testing environment. Current environment: [{$environment}].");
        }

        if (! $this->isSafeTestDatabase($defaultConnection, $databaseName)) {
            throw new RuntimeException(
                "Refusing to run tests against unsafe database configuration [connection={$defaultConnection}, database={$databaseName}]. "
                .'Configure PHPUnit to use sqlite `:memory:` or a dedicated database whose name clearly indicates it is for tests.'
            );
        }
    }

    protected function isSafeTestDatabase(string $connection, string $databaseName): bool
    {
        if ($connection === 'sqlite') {
            if ($databaseName === ':memory:') {
                return true;
            }

            $normalizedPath = str_replace('\\', '/', $databaseName);
            $basename = strtolower(basename($normalizedPath));

            return str_contains($basename, 'test');
        }

        $normalizedName = strtolower($databaseName);

        return str_contains($normalizedName, 'test');
    }
}
