<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Data\AbstractData;
use App\Models\Training\ExercisePlan\Actions\Casts\BlockActionCast;
use App\Models\Training\ExercisePlan\ExerciseBlock;
use Spatie\LaravelData\Attributes\WithCast;

class BlockResult extends AbstractData
{
    public string $actionClass;

    public function __construct(
        #[WithCast(BlockActionCast::class)]
        public BlockAction $action,
        public ExerciseBlock $previous,
        public ExerciseBlock $current,
        ?string $actionClass = null,
    ) {
        $this->actionClass = $actionClass ?? get_class($action);
    }

    public function title(): string
    {
        return $this->actionClass::getTitle();
    }

    public function helpText(): string
    {
        return $this->actionClass::helpText();
    }

    public function toArray(): array
    {
        return [
            'action' => $this->action->toArray(),
            'previous' => $this->previous->toArray(),
            'current' => $this->current->toArray(),
            'actionClass' => $this->actionClass,
        ];
    }

    public function getHighlightedCells(): array
    {
        $changed = [];
        $previousWeeks = $this->previous->weeks;
        $currentWeeks = $this->current->weeks;

        foreach ($currentWeeks as $weekIndex => $currentWeek) {
            $previousWeek = $previousWeeks[$weekIndex] ?? null;

            foreach ($currentWeek->sessions as $sessionIndex => $currentSession) {
                $previousSession = $previousWeek?->sessions[$sessionIndex] ?? null;

                foreach ($currentSession->sets as $setIndex => $currentSet) {
                    $previousSet = $previousSession?->sets[$setIndex] ?? null;

                    $previousReps = $previousSet?->reps;
                    $previousWeight = $previousSet?->weight;
                    $previousOneRepMax = $previousSet?->oneRepMax;

                    if ($currentSet->reps !== $previousReps) {
                        $changed["{$weekIndex}.{$sessionIndex}.{$setIndex}.reps"] = true;
                    }
                    if ($currentSet->weight !== $previousWeight) {
                        $changed["{$weekIndex}.{$sessionIndex}.{$setIndex}.weight"] = true;
                    }
                    if ($currentSet->oneRepMax !== $previousOneRepMax) {
                        $changed["{$weekIndex}.{$sessionIndex}.{$setIndex}.oneRepMax"] = true;
                    }
                }
            }
        }

        return $changed;
    }
}
