<?php

namespace Database\Seeders;

use App\Support\Import\ExerciseFixtureImporter;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $profile = (string) env('DB_SEED_PROFILE', 'content-import');

        match ($profile) {
            'content-import', '' => $this->runContentImportWithConallFixture(),
            'exercise-fixture' => $this->runConallFixtureOnly(),
            default => throw new InvalidArgumentException("Unknown DB_SEED_PROFILE [{$profile}]"),
        };

        $this->call([
            //   CoachSeeder::class,
            // PerformanceTestSeeder::class,
            // ExerciseExternalSeeder::class,
            CategoryShortNameSeeder::class,
        ]);
    }

    private function runContentImportWithConallFixture(): void
    {
        app(DatabaseImportSeeder::class)
            ->setContainer(app())
            ->setCommand($this->command)
            ->contentOnly()
            ->run();

        $this->runConallFixtureOnly();
    }

    private function runConallFixtureOnly(): void
    {
        app(ExerciseFixtureImporter::class)->import(base_path('import/fixture'), $this->command);
    }
}
