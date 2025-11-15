<?php

namespace App\Models\Users\Types;

use App\Data\Address;
use App\Data\MetricTypes\Athlete\OneRepMax;
use App\Data\MetricTypes\Generic\Percentage;
use App\Data\MetricTypes\Generic\Weight;
use App\Data\MetricTypes\Generic\Number;
use App\Models\Users\User;
use Parental\HasParent;
use Illuminate\Database\Eloquent\Model;
use App\Models\Metrics\Contracts\HasMetricTypes;
use App\Models\Metrics\Concerns\InteractsWithMetrics;


class Athlete extends User implements HasMetricTypes
{
    use HasParent;
    use InteractsWithMetrics;

    public function allowedGroupTypes(): array
    {
        return ['athlete'];
    }

    public static function getMetricTypes(): array
    {
        return [
            OneRepMax::class,
            Weight::class,
            Percentage::class,
            Number::class,
        ];
    }

    public static function getCustomMetricTypes(): array
    {
        return [
            OneRepMax::class,
            Weight::class,
            Percentage::class,
            Number::class,
        ];
    }

    public static function getExtraConfig(?Model $model = null): array
    {
        return [
            'address' => Address::class,
        ];
    }
}
