<?php

namespace Database\Seeders;

use App\Models\ProgramCategory;
use Illuminate\Database\Seeder;

class ProgramCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Strength',
            'Jump',
            'Core',
            'Endurance',
            'Coordination',
            'Recovery',
            'Miscellaneous',
        ];

        foreach ($categories as $sort => $name) {
            ProgramCategory::updateOrCreate(
                ['name' => $name],
                ['sort' => $sort],
            );
        }
    }
}
