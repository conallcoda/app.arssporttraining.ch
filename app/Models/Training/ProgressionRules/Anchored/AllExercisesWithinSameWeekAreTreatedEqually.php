<?php

namespace App\Models\Training\ProgressionRules\Anchored;

use App\Models\Training\Data\WeekData;
use App\Models\Training\ProgressionRules\AbstractProgressionRule;
use App\Models\Training\TrainingTree;

class AllExercisesWithinSameWeekAreTreatedEqually extends AbstractProgressionRule
{
    protected int $defaultSetCount = 4;
    protected float $defaultWeight = 50;
    protected array $defaultReps = [14, 14, 12, 12];

    public function apply(TrainingTree $tree, AnchoredProgression $progression): array
    {
        $anchorExerciseId = $progression->metrics['anchorExerciseId'] ?? null;
        $anchorSessionKey = $progression->metrics['anchorSessionKey'] ?? null;

        if (!$anchorExerciseId || !$anchorSessionKey) {
            return [];
        }

        $firstNonLinkedWeek = $this->getFirstNonLinkedWeek($tree);
        if (!$firstNonLinkedWeek) {
            return [];
        }

        $weeksUpdated = [];

        foreach ($this->getWeeks($tree) as $week) {
            $overrides = $week->data->progressionOverrides;
            $weekModified = false;

            $sourceOverrides = $overrides[$anchorSessionKey][$anchorExerciseId] ?? [];

            foreach ($this->getNonLinkedSessions($firstNonLinkedWeek) as $session) {
                $sessionData = $session->getData();
                $sessionKey = $sessionData->day . '-' . $sessionData->slot;

                if ($sessionKey === $anchorSessionKey) {
                    continue;
                }

                $exerciseIds = $sessionData->exercises ?? [];
                if (!in_array($anchorExerciseId, $exerciseIds)) {
                    continue;
                }

                if (!isset($overrides[$sessionKey])) {
                    $overrides[$sessionKey] = [];
                }
                if (!isset($overrides[$sessionKey][$anchorExerciseId])) {
                    $overrides[$sessionKey][$anchorExerciseId] = [];
                }

                foreach ($sourceOverrides as $setIndex => $setData) {
                    if (!isset($overrides[$sessionKey][$anchorExerciseId][$setIndex])) {
                        $overrides[$sessionKey][$anchorExerciseId][$setIndex] = [];
                    }
                    if (isset($setData['reps'])) {
                        $overrides[$sessionKey][$anchorExerciseId][$setIndex]['reps'] = $setData['reps'];
                    }
                    if (isset($setData['weight'])) {
                        $overrides[$sessionKey][$anchorExerciseId][$setIndex]['weight'] = $setData['weight'];
                    }
                }

                $weekModified = true;
            }

            if ($weekModified) {
                $week->data = WeekData::from([
                    'progressionOverrides' => $overrides,
                ]);
                $weeksUpdated[] = $week->uuid;
            }
        }

        if (!empty($weeksUpdated)) {
            $tree->markChanged();
        }

        return [
            'equalizedWeeks' => $weeksUpdated,
        ];
    }
}
