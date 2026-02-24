<?php

namespace App\Livewire\Training\View;

use App\Data\Training\Config\TrainingPlanConfig;
use App\Models\TrainingPlan;

class ScheduleHandler
{
    protected TrainingPlanConfig $config;

    public function __construct(
        protected TrainingPlan $trainingPlan,
        protected ?int $userId = null,
    ) {
        $this->config = TrainingPlanConfig::from($this->trainingPlan->config->toArray());
    }

    public function handle(string $type, array $data = []): void
    {
        match ($type) {
            'add-week' => $this->addWeek(),
            'remove-week' => $this->removeWeek($data['weekId']),
            'link-week' => $this->linkWeek($data['weekId'], $data['linkToWeekId'] ?? null),
            'unlink-week' => $this->unlinkWeek($data['weekId']),
            'assign-program' => $this->assignProgram($data['weekId'], $data['day'], $data['slot'], $data['programId']),
            'clear-slot' => $this->saveSlotChange($data['weekId'], $data['day'], $data['slot'], []),
            'move-program' => $this->moveProgram($data['weekId'], $data['fromDay'], $data['fromSlot'], $data['toDay'], $data['toSlot']),
            'move-single-program' => $this->moveSingleProgram($data['programId'], $data['fromWeekId'], $data['fromDay'], $data['fromSlot'], $data['toWeekId'], $data['toDay'], $data['toSlot']),
            'reorder-slot-program' => $this->reorderSlotProgram($data['weekId'], $data['day'], $data['slot'], $data['programId'], $data['targetProgramId'], $data['position']),
            'swap-programs' => $this->swapPrograms($data['week1Id'], $data['day1'], $data['slot1'], $data['week2Id'], $data['day2'], $data['slot2']),
            'remove-program-from-slots' => $this->removeProgramFromSlots($data['programId']),
            'unassign-program' => $this->unassignProgram($data['weekId'], $data['day'], $data['slot'], $data['programId']),
            'lock-schedule' => $this->lockSchedule(),
        };

        $this->save();
    }

    protected function getWeeks(): array
    {
        if ($this->userId !== null) {
            if ($this->config->isUserScheduleLocked($this->userId)) {
                $this->config->unlockUserSchedule($this->userId);
            }

            return $this->config->userScheduleWeeks($this->userId);
        }

        return $this->config->defaultScheduleWeeks();
    }

    protected function setWeeks(array $weeks): void
    {
        if ($this->userId !== null) {
            $this->config->setUserScheduleWeeks($this->userId, $weeks);
        } else {
            $this->config->setDefaultScheduleWeeks($weeks);
        }
    }

    protected function addWeek(): void
    {
        $weeks = $this->getWeeks();
        $week1Id = $weeks[0]['id'] ?? null;
        $maxSort = collect($weeks)->max('sort') ?? count($weeks) - 1;
        $newSort = $maxSort + 1;

        $prefix = $this->userId !== null ? 'user' : 'default';

        $weeks[] = [
            'id' => "{$prefix}_{$newSort}",
            'linkedTo' => $week1Id,
            'slots' => [],
            'sort' => $newSort,
        ];

        $this->setWeeks($weeks);
    }

    protected function removeWeek(string $weekId): void
    {
        $weeks = $this->getWeeks();

        if ($this->getWeekIndex($weeks, $weekId) === 0) {
            return;
        }

        $allWeeks = $weeks;
        $weeks = collect($weeks)
            ->filter(fn (array $week) => $week['id'] !== $weekId)
            ->map(function (array $week) use ($weekId, $allWeeks) {
                if (($week['linkedTo'] ?? null) === $weekId) {
                    $week['linkedTo'] = null;
                    $week['slots'] = $this->resolveLinkedSlotsRaw($week, $allWeeks);
                }

                return $week;
            })
            ->values()
            ->all();

        $this->setWeeks($weeks);
    }

    protected function linkWeek(string $weekId, ?string $linkToWeekId): void
    {
        $weeks = $this->getWeeks();

        if ($this->getWeekIndex($weeks, $weekId) === 0) {
            return;
        }

        foreach ($weeks as &$week) {
            if ($week['id'] === $weekId) {
                $week['linkedTo'] = $linkToWeekId;
                if ($linkToWeekId !== null) {
                    $week['slots'] = [];
                }
                break;
            }
        }

        $this->setWeeks($weeks);
    }

    protected function unlinkWeek(string $weekId): void
    {
        $weeks = $this->getWeeks();

        foreach ($weeks as &$week) {
            if ($week['id'] === $weekId && ($week['linkedTo'] ?? null) !== null) {
                $resolvedSlots = $this->resolveLinkedSlotsRaw($week, $weeks);
                $week['slots'] = $this->filterNonEmptySlots($resolvedSlots);
                $week['linkedTo'] = null;
                break;
            }
        }

        $this->setWeeks($weeks);
    }

    protected function lockSchedule(): void
    {
        if ($this->userId === null) {
            return;
        }

        $this->config->lockUserSchedule($this->userId);
    }

    protected function assignProgram(string $weekId, int $day, int $slot, int $programId): void
    {
        $weeks = $this->getWeeks();

        foreach ($weeks as &$week) {
            if ($week['id'] === $weekId) {
                $existing = $this->findSlot($week['slots'] ?? [], $day, $slot);
                $programs = $existing['programs'] ?? [];

                if (! in_array($programId, $programs)) {
                    $programs[] = $programId;
                }

                $this->setSlot($week['slots'], $day, $slot, $programs);
                break;
            }
        }

        $this->setWeeks($weeks);
    }

    protected function moveProgram(string $weekId, int $fromDay, int $fromSlot, int $toDay, int $toSlot): void
    {
        $weeks = $this->getWeeks();
        $week = collect($weeks)->firstWhere('id', $weekId);

        if (! $week || ($week['linkedTo'] ?? null) !== null) {
            return;
        }

        $fromSlotData = $this->findSlot($week['slots'] ?? [], $fromDay, $fromSlot);
        $programs = $fromSlotData['programs'] ?? [];

        $this->saveSlotChange($weekId, $fromDay, $fromSlot, []);
        $this->saveSlotChange($weekId, $toDay, $toSlot, $programs);
    }

    protected function moveSingleProgram(int $programId, string $fromWeekId, int $fromDay, int $fromSlot, string $toWeekId, int $toDay, int $toSlot): void
    {
        $this->unassignProgram($fromWeekId, $fromDay, $fromSlot, $programId);
        $this->assignProgram($toWeekId, $toDay, $toSlot, $programId);
    }

    protected function reorderSlotProgram(string $weekId, int $day, int $slot, int $programId, int $targetProgramId, string $position): void
    {
        $weeks = $this->getWeeks();

        foreach ($weeks as &$week) {
            if ($week['id'] !== $weekId) {
                continue;
            }

            $existing = $this->findSlot($week['slots'] ?? [], $day, $slot);
            $programs = $existing['programs'] ?? [];

            $programs = array_values(array_filter($programs, fn (int $id) => $id !== $programId));

            $targetIndex = array_search($targetProgramId, $programs);
            if ($targetIndex === false) {
                $programs[] = $programId;
            } else {
                if ($position === 'after') {
                    $targetIndex++;
                }
                array_splice($programs, $targetIndex, 0, [$programId]);
            }

            $this->setSlot($week['slots'], $day, $slot, $programs);
            break;
        }

        $this->setWeeks($weeks);
    }

    protected function swapPrograms(string $week1Id, int $day1, int $slot1, string $week2Id, int $day2, int $slot2): void
    {
        $weeks = $this->getWeeks();
        $week1 = collect($weeks)->firstWhere('id', $week1Id);
        $week2 = collect($weeks)->firstWhere('id', $week2Id);

        if (! $week1 || ! $week2 || ($week1['linkedTo'] ?? null) !== null || ($week2['linkedTo'] ?? null) !== null) {
            return;
        }

        $slot1Data = $this->findSlot($week1['slots'] ?? [], $day1, $slot1);
        $slot2Data = $this->findSlot($week2['slots'] ?? [], $day2, $slot2);

        $this->saveSlotChange($week1Id, $day1, $slot1, $slot2Data['programs'] ?? []);
        $this->saveSlotChange($week2Id, $day2, $slot2, $slot1Data['programs'] ?? []);
    }

    protected function removeProgramFromSlots(int $programId): void
    {
        $weeks = $this->getWeeks();

        foreach ($weeks as &$week) {
            if (($week['linkedTo'] ?? null) !== null) {
                continue;
            }

            foreach ($week['slots'] ?? [] as &$slot) {
                $slot['programs'] = array_values(array_filter(
                    $slot['programs'] ?? [],
                    fn (int $id) => $id !== $programId
                ));
            }

            $week['slots'] = $this->filterNonEmptySlots($week['slots'] ?? []);
        }

        $this->setWeeks($weeks);
    }

    protected function unassignProgram(string $weekId, int $day, int $slot, int $programId): void
    {
        $weeks = $this->getWeeks();

        foreach ($weeks as &$week) {
            if ($week['id'] !== $weekId) {
                continue;
            }

            $existing = $this->findSlot($week['slots'] ?? [], $day, $slot);
            $programs = array_values(array_filter(
                $existing['programs'] ?? [],
                fn (int $id) => $id !== $programId
            ));

            $this->setSlot($week['slots'], $day, $slot, $programs);
            break;
        }

        $this->setWeeks($weeks);
    }

    protected function saveSlotChange(string $weekId, int $day, int $slot, array $programs): void
    {
        $weeks = $this->getWeeks();

        foreach ($weeks as &$week) {
            if ($week['id'] === $weekId) {
                $this->setSlot($week['slots'], $day, $slot, $programs);
                break;
            }
        }

        $this->setWeeks($weeks);
    }

    protected function resolveLinkedSlotsRaw(array $week, array $allWeeks): array
    {
        if (($week['linkedTo'] ?? null) === null) {
            return $week['slots'] ?? [];
        }

        $sourceWeek = collect($allWeeks)->firstWhere('id', $week['linkedTo']);

        return $sourceWeek ? $this->resolveLinkedSlotsRaw($sourceWeek, $allWeeks) : ($week['slots'] ?? []);
    }

    protected function filterNonEmptySlots(array $slots): array
    {
        return array_values(array_filter($slots, fn (array $slot) => ! empty($slot['programs'] ?? [])));
    }

    protected function findSlot(array $slots, int $day, int $slot): ?array
    {
        foreach ($slots as $s) {
            if ($s['day'] === $day && $s['slot'] === $slot) {
                return $s;
            }
        }

        return null;
    }

    /** @param int[] $programs */
    protected function setSlot(array &$slots, int $day, int $slot, array $programs): void
    {
        $this->removeSlot($slots, $day, $slot);

        if (! empty($programs)) {
            $slots[] = ['day' => $day, 'slot' => $slot, 'programs' => $programs];
        }
    }

    protected function removeSlot(array &$slots, int $day, int $slot): void
    {
        $slots = array_values(array_filter($slots, fn (array $s) => ! ($s['day'] === $day && $s['slot'] === $slot)));
    }

    protected function getWeekIndex(array $weeks, string $weekId): ?int
    {
        foreach ($weeks as $index => $week) {
            if (($week['id'] ?? null) === $weekId) {
                return $index;
            }
        }

        return null;
    }

    public static function isProgramUsedInConfig(TrainingPlan $trainingPlan, int $programId, array $userIds): bool
    {
        $config = $trainingPlan->config;

        foreach ($config->defaultScheduleWeeks() as $week) {
            foreach ($week['slots'] ?? [] as $slot) {
                if (in_array($programId, $slot['programs'] ?? [])) {
                    return true;
                }
            }
        }

        foreach ($userIds as $userId) {
            foreach ($config->userScheduleWeeks($userId) as $week) {
                foreach ($week['slots'] ?? [] as $slot) {
                    if (in_array($programId, $slot['programs'] ?? [])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    protected function save(): void
    {
        $this->trainingPlan->config = $this->config;
        $this->trainingPlan->save();
    }
}
