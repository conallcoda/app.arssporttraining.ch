<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Exercise\Exercise;
use App\Models\Training\Periods\TrainingSeason;
use App\Models\Training\TrainingSessionCategory;
use Illuminate\Support\Str;


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

        $e1 = Exercise::find(61);
        $e2 = Exercise::find(62);
        $e3 = Exercise::find(63);
        $e4 = Exercise::find(64);

        $dto = TrainingSeason::from([
            'name' => 'Example Training Plan',
            'children' => [
                [
                    'children' => [
                        [
                            'children' => [
                                [
                                    'category' => $categoryModels['gym'],
                                    'period' => [
                                        'day' => 0,
                                        'sequence' => 1,
                                    ],
                                    'children' => [
                                        [
                                            'exercise' => $e1,
                                        ],
                                        [
                                            'exercise' => $e2,
                                        ],
                                    ]
                                ]
                            ]
                        ],
                        [],
                    ],
                ],
                [
                    'children' => [
                        [],
                        []
                    ],
                ],
            ],
        ]);
        $dto->persist();
    }
}
