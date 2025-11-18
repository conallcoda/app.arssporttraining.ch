<?php

namespace App\Livewire;

use App\Models\Exercise\Exercise;
use App\Models\Training\Periods\Data\TrainingSessionCategory;
use App\Models\Training\Periods\TrainingSeason;
use Livewire\Component;

class TrainingPlanner extends Component
{
    public $season = null;

    public function mount()
    {
        $radomNumber = random_int(500, 10000);

        $dto = TrainingSeason::from([
            'name' => 'fish' . $radomNumber,
            'children' => [],
        ]);

        $dto->persist();
        dd($dto);


        $gym = TrainingSessionCategory::from([
            'name' => 'Gym',
            'backgroundColor' => '#ff0000',
            'textColor' => '#ffffff',
        ]);

        $core = TrainingSessionCategory::from([
            'name' => 'Core',
            'backgroundColor' => '#0000ff',
            'textColor' => '#000000',
        ]);



        $e1 = Exercise::find(61);
        $e2 = Exercise::find(62);
        $e3 = Exercise::find(63);
        $e4 = Exercise::find(64);

        $dto = TrainingSeason::from([
            'name' => 'fish' . $radomNumber,
            'children' => [
                [
                    'children' => [
                        [
                            'children' => [
                                [
                                    'category' => $gym,
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
        dd($dto);
        $dto = TrainingSeason::from(
            [
                'name' => 'fish' . $radomNumber,
                'children' => [
                    [
                        'children' => [
                            [
                                'children' => [
                                    [
                                        'children' => [
                                            [],
                                            [],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'children' => [],
                            ],
                        ],
                    ],
                    [
                        'children' => [
                            [
                                'children' => [],
                            ],
                            [
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ]
        );

        dd($dto);
    }

    public function render()
    {
        return view('training-planner');
    }
}
