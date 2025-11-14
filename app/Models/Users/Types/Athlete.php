<?php

namespace App\Models\Users\Types;

use App\Data\Address;
use App\Models\Metrics\Metric;
use App\Models\Users\User;
use Parental\HasParent;
use Illuminate\Database\Eloquent\Model;
use App\Models\Metrics\Contracts\HasMetricTypes;

class Athlete extends User implements HasMetricTypes
{
    use HasParent;

    public function allowedGroupTypes(): array
    {
        return ['athlete'];
    }

    public static function getAllowedMetricTypes(): array
    {
        return Metric::genericsAnd(['weight', 'height', 'one_rep_max']);
    }

    public static function getExtraConfig(?Model $model = null): array
    {
        return [
            'address' => Address::class,
        ];
    }
}
