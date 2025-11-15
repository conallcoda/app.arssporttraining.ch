<?php

namespace App\Models\Metrics\Types;

use App\Models\Metrics\MetricType;
use Parental\HasParent;

class Duration extends MetricType {
    use HasParent;
}
