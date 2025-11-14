<?php

namespace App\Models\Training\Periods;

use App\Models\Training\TrainingPeriod;
use Parental\HasParent;
use App\Models\Training\Periods\Contracts\HasChildren;

class TrainingSeason extends TrainingPeriod implements HasChildren
{
    use HasParent;

    public static function addChildForm(): array
    {
        return [
            'allowed_types' => [TrainingBlock::class],
            'fields' => [
                'num_weeks' => [
                    'type' => 'number',
                    'label' => 'Number of Weeks',
                    'default' => 4,
                    'attributes' => [
                        'min' => 1,
                        'step' => 1,
                    ],
                ],
            ],
        ];
    }
}
