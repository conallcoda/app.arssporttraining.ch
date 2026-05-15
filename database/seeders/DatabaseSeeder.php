<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(DatabaseImportSeeder::class)
            ->setContainer(app())
            ->setCommand($this->command)
            ->run();

        $this->call([
            //   CoachSeeder::class,
            // PerformanceTestSeeder::class,
            // ExerciseExternalSeeder::class,
            CategoryShortNameSeeder::class,
        ]);
    }
}
