<?php

namespace App\Models\Training\Periods;

use App\Models\Training\TrainingPeriod;
use Parental\HasParent;
use App\Models\Training\Periods\Contracts\HasChildren;

class TrainingWeek extends TrainingPeriod implements HasChildren
{
    use HasParent;

    public static function addChildForm(): array
    {
        return [
            'allowed_types' => [TrainingSession::class],
            'fields' => [
                'name' => [
                    'type' => 'select',
                    'label' => 'Name',
                    'options' => [
                        'core' => 'Core',
                        'coordination' => 'Coordination',
                        'jump' => 'Jump',
                        'strength' => 'Strength',
                    ]
                ],
            ],
        ];
    }
}
