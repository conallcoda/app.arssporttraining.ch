<?php

namespace Coda\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'cms:install {--migrate : Run migrations after installing}';

    protected $description = 'Install the CMS package';

    public function handle(): int
    {
        $this->info('Installing CMS package...');

        $this->configureEnv();
        $this->createDatabase();
        $this->replaceDefaultMigration();
        $this->publishConfig();
        $this->publishMigrations();
        $this->scaffoldApp();

        if ($this->option('migrate')) {
            $this->call('migrate');
            $this->info('Migrations complete.');
        }

        $this->newLine();
        $this->info('CMS installed successfully.');
        $this->newLine();
        $this->line('  Run: php artisan db:seed');
        $this->line('  Then: npm install && npm run build');
        $this->newLine();
        $this->line('  To customise layouts/auth views:');
        $this->line('  php artisan vendor:publish --tag=cms-views');

        return self::SUCCESS;
    }

    protected function configureEnv(): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return;
        }

        $dbName = str_replace('-', '_', Str::snake(basename(base_path())));

        $this->setEnvValue($envPath, 'DB_CONNECTION', 'mysql');
        $this->setEnvValue($envPath, 'DB_HOST', '127.0.0.1');
        $this->setEnvValue($envPath, 'DB_PORT', '3306');
        $this->setEnvValue($envPath, 'DB_DATABASE', $dbName);
        $this->setEnvValue($envPath, 'DB_USERNAME', 'root');
        $this->setEnvValue($envPath, 'DB_PASSWORD', '');

        $this->info("Configured .env (database: {$dbName}).");

        $this->laravel['config']->set('database.default', 'mysql');
        $this->laravel['config']->set('database.connections.mysql.host', '127.0.0.1');
        $this->laravel['config']->set('database.connections.mysql.port', '3306');
        $this->laravel['config']->set('database.connections.mysql.database', $dbName);
        $this->laravel['config']->set('database.connections.mysql.username', 'root');
        $this->laravel['config']->set('database.connections.mysql.password', '');
    }

    protected function setEnvValue(string $envPath, string $key, string $value): void
    {
        $contents = file_get_contents($envPath);

        $quoted = str_contains($value, ' ') ? "\"{$value}\"" : $value;

        if (preg_match("/^{$key}=.*/m", $contents)) {
            $contents = preg_replace("/^{$key}=.*/m", "{$key}={$quoted}", $contents);
        } else {
            $contents .= "\n{$key}={$quoted}";
        }

        file_put_contents($envPath, $contents);
    }

    protected function replaceDefaultMigration(): void
    {
        $defaultMigration = database_path('migrations/0001_01_01_000000_create_users_table.php');

        if (file_exists($defaultMigration)) {
            unlink($defaultMigration);
            $this->info('Removed default Laravel users migration (replaced by CMS).');
        }
    }

    protected function publishConfig(): void
    {
        $this->call('vendor:publish', ['--tag' => 'cms-config']);
        $this->info('Published config/cms.php.');
    }

    protected function publishMigrations(): void
    {
        $this->call('vendor:publish', ['--tag' => 'cms-migrations']);
        $this->info('Published migrations.');
    }

    protected function scaffoldApp(): void
    {
        $stubsPath = __DIR__.'/../../stubs';

        $this->copyStub($stubsPath.'/app/Models/User.php', app_path('Models/User.php'));
        $this->copyStub($stubsPath.'/CmsServiceProvider.php', app_path('Providers/CmsServiceProvider.php'));
        $this->copyStub($stubsPath.'/routes/web.php', base_path('routes/web.php'));
        $this->copyStub($stubsPath.'/bootstrap/app.php', base_path('bootstrap/app.php'));
        $this->copyStub($stubsPath.'/database/seeders/DatabaseSeeder.php', database_path('seeders/DatabaseSeeder.php'));
        $this->copyStub($stubsPath.'/resources/js/app.js', resource_path('js/app.js'));
        $this->copyStub($stubsPath.'/resources/css/app.css', resource_path('css/app.css'));

        $this->copyDirectory($stubsPath.'/resources/views/flux/icon', resource_path('views/flux/icon'));

        $this->registerProvider();
        $this->installNpmDependencies();

        $this->info('Scaffolded app (provider, routes, bootstrap, seeder, JS, CSS, icons).');
    }

    protected function copyDirectory(string $from, string $to): void
    {
        if (! is_dir($to)) {
            mkdir($to, 0755, true);
        }

        foreach (glob($from.'/*') as $file) {
            copy($file, $to.'/'.basename($file));
        }
    }

    protected function copyStub(string $from, string $to): void
    {
        $directory = dirname($to);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        copy($from, $to);
    }

    protected function installNpmDependencies(): void
    {
        $result = Process::run(['npm', 'install', '--save', 'treeselectjs', '@tailwindcss/typography']);

        if ($result->successful()) {
            $this->info('Installed npm dependencies (treeselectjs, @tailwindcss/typography).');
        } else {
            $this->warn('Failed to install npm dependencies. Run manually: npm install treeselectjs @tailwindcss/typography');
        }
    }

    protected function registerProvider(): void
    {
        $providersPath = base_path('bootstrap/providers.php');

        if (! file_exists($providersPath)) {
            return;
        }

        $contents = file_get_contents($providersPath);

        if (str_contains($contents, 'CmsServiceProvider')) {
            return;
        }

        if (str_contains($contents, 'AppServiceProvider::class')) {
            $contents = preg_replace(
                '/AppServiceProvider::class,/',
                "AppServiceProvider::class,\n    \\App\\Providers\\CmsServiceProvider::class,",
                $contents,
                1,
            );
        } else {
            $contents = str_replace(
                "return [\n",
                "return [\n    \\App\\Providers\\CmsServiceProvider::class,\n",
                $contents,
            );
        }

        file_put_contents($providersPath, $contents);
    }

    protected function createDatabase(): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver !== 'mysql') {
            return;
        }

        $database = config("database.connections.{$connection}.database");
        $host = config("database.connections.{$connection}.host", '127.0.0.1');
        $port = config("database.connections.{$connection}.port", '3306');
        $username = config("database.connections.{$connection}.username", 'root');
        $password = config("database.connections.{$connection}.password", '');

        $mysqlBinary = collect([
            '/Users/Shared/DBngin/mariadb/10.11.6_arm/bin/mysql',
            '/opt/homebrew/bin/mysql',
            '/usr/local/bin/mysql',
            'mysql',
        ])->first(fn (string $path) => $path === 'mysql' || file_exists($path));

        $command = [
            $mysqlBinary,
            '-u', $username,
            '-h', $host,
            '-P', (string) $port,
            '-e', "CREATE DATABASE IF NOT EXISTS `{$database}`",
        ];

        if ($password !== '') {
            $command[] = "-p{$password}";
        }

        $result = Process::run($command);

        if ($result->successful()) {
            $this->info("Database '{$database}' ready.");
        } else {
            $this->warn("Could not create database '{$database}': ".$result->errorOutput());
            $this->warn('You may need to create it manually.');
        }
    }
}
