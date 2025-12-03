<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;
use App\Models\Training\ExercisePlan\Actions\BlockResult;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class ExerciseBlockManager extends AbstractData
{
    public function __construct(
        public AthleteData $athlete,
        public ExerciseData $exercise,
        #[DataCollectionOf(BlockResult::class)]
        public array $results = [],
    ) {}

    public static function example(?AthleteData $athlete = null, ?ExerciseData $exercise = null): self
    {
        $athlete = $athlete ?? AthleteData::example();
        $exercise = $exercise ?? ExerciseData::front_squat();

        $current = ExerciseBlock::example();
        $actions = [
            new Actions\CreateEmptyBlock(),
        ];

        foreach ($actions as $action) {
            $result = $action->apply($current);
            $current = $result->current;
            $results[] = $result;
        }

        return new self(
            athlete: $athlete,
            exercise: $exercise,
            results: $results ?? [],
        );
    }

    public function current(): ExerciseBlock
    {
        return end($this->results)->current;
    }
}
