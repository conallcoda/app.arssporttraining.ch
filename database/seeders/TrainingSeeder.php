<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Training\TrainingSessionCategory;
use Illuminate\Support\Facades\Artisan;


class TrainingSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Gym', 'text_color' => 'white', 'background_color' => 'blue'],
            ['name' => 'Jump', 'text_color' => 'white', 'background_color' => 'red'],
            ['name' => 'Core', 'text_color' => 'black', 'background_color' => 'yellow'],
            ['name' => 'Coordination', 'text_color' => 'white', 'background_color' => 'green'],
        ];

        $categoryModels = [];
        foreach ($categories as $category) {
            $created = TrainingSessionCategory::create($category);
            $categoryModels[$created->slug] = $created;
        }

        Artisan::call('training:import');
    }
}
