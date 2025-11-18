<?php

namespace App\Models\Training;

use App\Data\AbstractData;
use Spatie\LaravelData\Attributes\Computed;
use Illuminate\Database\Eloquent\Builder;

class TrainingPeriodManager extends AbstractData
{

    #[Computed]
    public TrainingPeriod $tree;

    public function __construct(int $rootId)
    {
        $tree = TrainingPeriod::withInitialQueryConstraint(function (Builder $query) {
            $query->where('id', 11);
        }, function () {
            return TrainingPeriod::tree()->get();
        });

        $dtos = [];
        foreach ($tree as $model) {
            $dto = $model->toData();
            $dtos[$dto->identity->uuid] = $dto;
        }
        dd($dtos);
    }
}
