<?php

namespace App\Services\Training;

use App\Models\Training\TrainingNode;

class SessionFinder
{
    public function __construct(
        protected TrainingNode $block
    ) {}

    public function atPosition(TrainingNode $week, int $day, int $slot): ?TrainingNode
    {
        foreach ($week->getChildren() as $session) {
            $data = $session->getData();
            if ($data->day === $day && $data->slot === $slot) {
                return $session;
            }
        }

        return null;
    }

    public function byUuid(string $uuid): ?TrainingNode
    {
        foreach ($this->block->getChildren() as $week) {
            foreach ($week->getChildren() as $session) {
                if ($session->uuid === $uuid) {
                    return $session;
                }
            }
        }

        return null;
    }

    public function inWeek(string $weekUuid, int $day, int $slot): ?TrainingNode
    {
        foreach ($this->block->getChildren() as $week) {
            if ($week->uuid !== $weekUuid) {
                continue;
            }

            return $this->atPosition($week, $day, $slot);
        }

        return null;
    }
}
