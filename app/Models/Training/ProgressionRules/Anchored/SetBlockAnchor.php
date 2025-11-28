<?php

namespace App\Models\Training\ProgressionRules\Anchored;

use App\Models\Training\Data\WeekData;
use App\Models\Training\ProgressionRules\AbstractProgressionRule;
use App\Models\Training\TrainingTree;

class SetBlockAnchor extends AbstractProgressionRule
{
    protected int $defaultSetCount = 4;

    public function apply(TrainingTree $tree, AnchoredProgression $progression): array
    {
        $firstNonLinkedWeek = $this->getFirstNonLinkedWeek($tree);
        if (!$firstNonLinkedWeek) {
            return [];
        }

        $lastWeek = $this->getLastWeek($tree);
        if (!$lastWeek) {
            return [];
        }

        $lastSession = $this->getLastNonLinkedSession($firstNonLinkedWeek);
        if (!$lastSession) {
            return [];
        }

        $exerciseIds = $lastSession->getData()->exercises ?? [];
        if (empty($exerciseIds)) {
            return [];
        }

        $lastExerciseId = end($exerciseIds);

        $targetWeight = $progression->start + ($progression->start * $progression->increase / 100);
        $targetWeight = floor($targetWeight / $progression->increaseStep) * $progression->increaseStep;

        $sessionData = $lastSession->getData();
        $sessionKey = $sessionData->day . '-' . $sessionData->slot;
        $setIndex = $this->defaultSetCount - 1;

        $overrides = $lastWeek->data->progressionOverrides;
        if (!isset($overrides[$sessionKey])) {
            $overrides[$sessionKey] = [];
        }
        if (!isset($overrides[$sessionKey][$lastExerciseId])) {
            $overrides[$sessionKey][$lastExerciseId] = [];
        }
        if (!isset($overrides[$sessionKey][$lastExerciseId][$setIndex])) {
            $overrides[$sessionKey][$lastExerciseId][$setIndex] = [];
        }

        $overrides[$sessionKey][$lastExerciseId][$setIndex]['weight'] = $targetWeight;

        $lastWeek->data = WeekData::from([
            'progressionOverrides' => $overrides,
        ]);

        $tree->markChanged();

        return [
            'anchorWeekUuid' => $lastWeek->uuid,
            'anchorSessionKey' => $sessionKey,
            'anchorExerciseId' => $lastExerciseId,
            'anchorSetIndex' => $setIndex,
            'anchorWeight' => $targetWeight,
        ];
    }
}
