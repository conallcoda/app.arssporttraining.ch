<?php

namespace App\Models\Exercise\Types;

use App\Models\Exercise\Exercise;
use App\Models\Metrics\Contracts\HasMetricTypes;
use Parental\HasParent;
use App\Models\Metrics\Concerns\InteractsWithMetrics;

class StretchingExercise extends Exercise implements HasMetricTypes
{
    use HasParent;
    use InteractsWithMetrics;
}
