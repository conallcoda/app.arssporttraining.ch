<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class ExerciseSeeder extends Seeder
{

    public function run(): void
    {
        Artisan::call('exercise:import');
    }
}
