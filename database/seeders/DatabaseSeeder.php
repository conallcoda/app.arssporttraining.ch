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
        $this->call([
            UserSeeder::class,
            ExerciseCategorySeeder::class,
            // ExerciseSeeder::class,
            TagSeeder::class,
            ExerciseTemplateSeeder::class,
            ExerciseExternalSeeder::class,
            ProgramCategorySeeder::class,
            ExampleTrainingPlanSeeder::class,
        ]);
    }
}
