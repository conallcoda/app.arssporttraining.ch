<?php

namespace App\Livewire;

use App\Models\Training\Periods\TrainingSeason;
use Livewire\Component;

class TrainingPlanner extends Component
{
    public $season = null;

    public function mount()
    {
        $radomNumber = random_int(500, 10000);
        $dto = TrainingSeason::from(
            [
                'name' => 'fish' . $radomNumber,
                'children' => [
                    [
                        'name' => 'block 1',
                        'children' => [
                            [
                                'children' => [],
                            ],
                            [
                                'children' => [],
                            ],
                        ],
                    ],
                    [
                        'name' => 'block 2',
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
    }

    public function render()
    {
        return view('training-planner');
    }
}
