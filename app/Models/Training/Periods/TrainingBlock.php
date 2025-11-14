<?php

namespace App\Models\Training\Periods;

use App\Models\Training\TrainingPeriod;
use Parental\HasParent;
use App\Models\Training\Periods\Contracts\HasChildren;

class TrainingBlock extends TrainingPeriod implements HasChildren
{
    use HasParent;

    public static function addChildForm(): array
    {
        return [
            'allowed_types' => [TrainingWeek::class]
        ];
    }
}
