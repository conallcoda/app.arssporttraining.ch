<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseImportSeeder;
use Illuminate\Console\Command;

class ImportContentDatabaseCommand extends Command
{
    protected $signature = 'db:import-content {path? : Import directory path or timestamp folder inside import/database}';

    protected $description = 'Import database content without calendar data or manual program overrides';

    public function handle(): int
    {
        $seeder = app(DatabaseImportSeeder::class)
            ->setContainer(app())
            ->setCommand($this)
            ->contentOnly();

        $path = $this->argument('path');

        if (is_string($path) && $path !== '') {
            $seeder->usingImportPath($this->resolveImportPath($path));
        }

        $seeder->run();

        return self::SUCCESS;
    }

    private function resolveImportPath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path('import/database/'.$path);
    }
}
