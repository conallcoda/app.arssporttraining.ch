<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class TrainingSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('training:import');
    }
}
