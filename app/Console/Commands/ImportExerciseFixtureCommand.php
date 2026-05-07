<?php

namespace App\Console\Commands;

use App\Support\Import\ExerciseFixtureImporter;
use Illuminate\Console\Command;

class ImportExerciseFixtureCommand extends Command
{
    protected $signature = 'db:import-exercise-fixture {path? : Import directory path or folder inside import/fixture}';

    protected $description = 'Import a compact representative exercise fixture set for development';

    public function handle(): int
    {
        $path = $this->argument('path');

        $importPath = is_string($path) && $path !== ''
            ? $this->resolveImportPath($path)
            : base_path('import/fixture');

        app(ExerciseFixtureImporter::class)->import($importPath, $this);

        return self::SUCCESS;
    }

    private function resolveImportPath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path('import/fixture/'.$path);
    }
}
