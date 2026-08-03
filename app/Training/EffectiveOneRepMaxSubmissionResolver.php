<?php

namespace App\Training;

use App\Data\Athlete\Metric\MetricEnum;
use App\Models\Athlete\MetricSubmission;
use App\Models\Users\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class EffectiveOneRepMaxSubmissionResolver
{
    public function resolve(int $userId, CarbonInterface|string $cutoffDate): ?MetricSubmission
    {
        $date = $cutoffDate instanceof CarbonInterface
            ? $cutoffDate->format('Y-m-d')
            : $cutoffDate;

        $query = MetricSubmission::query()
            ->forAthlete($userId)
            ->forMetric(MetricEnum::OneRepMax)
            ->whereDate('recorded_at', '<=', $date);

        return $this->applyOrdering($query)
            ->with('values')
            ->first();
    }

    public function applyOrdering(Builder $query): Builder
    {
        return $query
            ->orderByDesc('recorded_at')
            ->orderByRaw(
                'CASE WHEN owner_type IS NULL OR owner_type = ? THEN 0 ELSE 1 END',
                [User::class],
            )
            ->orderByDesc('id');
    }
}
