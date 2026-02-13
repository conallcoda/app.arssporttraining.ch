<?php

namespace Database\Seeders;

use App\Models\ProgramCategory;
use Illuminate\Database\Seeder;

class ProgramCategorySeeder extends Seeder
{
    public function run(): void
    {



        $categories = [
            'Strength'    => 'red',
            'Jump'        => 'amber',
            'Core'        => 'blue',
            'Endurance'   => 'purple',
            'Coordination' => 'orange',
            'Recovery'    => 'green',
            'Miscellaneous' => 'gray',
        ];

        $sort = 0;
        foreach ($categories as $name => $color) {
            ProgramCategory::updateOrCreate(
                ['name' => $name],
                ['sort' => $sort, 'color' => $color],
            );
            $sort++;
        }
    }
}
