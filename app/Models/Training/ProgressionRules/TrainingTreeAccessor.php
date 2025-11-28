<?php

namespace App\Models\Training\ProgressionRules;

use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingTree;

trait TrainingTreeAccessor
{
    protected function getWeeks(TrainingTree $tree): array
    {
        if ($tree->root->type !== 'block') {
            return [];
        }

        return $tree->root->children;
    }

    protected function getFirstNonLinkedWeek(TrainingTree $tree): ?TrainingNode
    {
        $weeks = $this->getWeeks($tree);

        foreach ($weeks as $week) {
            if (!$week->isLinked()) {
                return $week;
            }
        }

        return null;
    }

    protected function getLastWeek(TrainingTree $tree): ?TrainingNode
    {
        $weeks = $this->getWeeks($tree);

        if (empty($weeks)) {
            return null;
        }

        return end($weeks);
    }

    protected function getNonLinkedSessions(TrainingNode $week): array
    {
        $sessions = $week->getChildren();

        if (empty($sessions)) {
            return [];
        }

        return array_filter($sessions, fn($s) => !$s->isLinked());
    }

    protected function getSessionsSortedByDaySlot(array $sessions): array
    {
        usort($sessions, function ($a, $b) {
            $aData = $a->getData();
            $bData = $b->getData();
            if ($aData->day !== $bData->day) {
                return $aData->day <=> $bData->day;
            }
            return $aData->slot <=> $bData->slot;
        });

        return $sessions;
    }

    protected function getLastNonLinkedSession(TrainingNode $week): ?TrainingNode
    {
        $sessions = $this->getNonLinkedSessions($week);

        if (empty($sessions)) {
            return null;
        }

        $sorted = $this->getSessionsSortedByDaySlot($sessions);

        return end($sorted);
    }

    protected function getExercises(TrainingNode $session): array
    {
        return $session->getChildren();
    }

    protected function getLastExercise(TrainingNode $session): ?TrainingNode
    {
        $exercises = $this->getExercises($session);

        if (empty($exercises)) {
            return null;
        }

        return end($exercises);
    }

    protected function getSets(TrainingNode $exercise): array
    {
        return $exercise->getChildren();
    }

    protected function getLastSet(TrainingNode $exercise): ?TrainingNode
    {
        $sets = $this->getSets($exercise);

        if (empty($sets)) {
            return null;
        }

        return end($sets);
    }

    protected function getSetCount(TrainingNode $exercise): int
    {
        return count($this->getSets($exercise));
    }
}
