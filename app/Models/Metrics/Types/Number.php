<?php

namespace App\Models\Metrics\Types;

use App\Models\Metrics\MetricType;
use Parental\HasParent;

class Number extends MetricType {
    use HasParent;
}
