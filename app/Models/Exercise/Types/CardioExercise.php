<?php

namespace App\Models\Exercise\Types;

use App\Models\Exercise\Exercise;
use App\Models\Metrics\Metric;
use App\Models\Metrics\Contracts\HasMetricTypes;
use Parental\HasParent;

class CardioExercise extends Exercise implements HasMetricTypes
{
    use HasParent;

    public static function getAllowedMetricTypes(): array
    {
        return Metric::genericsAnd(['duration']);
    }
}
