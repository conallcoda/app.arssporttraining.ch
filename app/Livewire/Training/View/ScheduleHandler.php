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
        $this->config = TrainingPlanConfig::from($this->trainingPlan->config->all());
    }

    public function handle(string $type, array $data = []): void
    {
        match ($type) {
            'add-week' => $this->addWeek(),
            'remove-week' => $this->removeWeek($data['weekId']),
            'link-week' => $this->linkWeek($data['weekId'], $data['linkToWeekId'] ?? null),
            'unlink-week' => $this->unlinkWeek($data['weekId']),
            'relink-week' => $this->relinkWeek($data['weekId']),
            'reset-to-default' => $this->resetToDefault(),
            'assign-program' => $this->assignProgram($data['weekId'], $data['day'], $data['slot'], $data['programId']),
            'clear-slot' => $this->saveSlotChange($data['weekId'], $data['day'], $data['slot'], null),
            'move-program' => $this->moveProgram($data['weekId'], $data['fromDay'], $data['fromSlot'], $data['toDay'], $data['toSlot']),
            'swap-programs' => $this->swapPrograms($data['week1Id'], $data['day1'], $data['slot1'], $data['week2Id'], $data['day2'], $data['slot2']),
            'remove-program-from-slots' => $this->removeProgramFromSlots($data['programId']),
        };

        $this->save();
    }

    // --- Week structure ---

    protected function addWeek(): void
    {
        if ($this->userId !== null) {
            $this->addUserWeek();

            return;
        }

        $this->addDefaultWeek();
    }

    protected function addDefaultWeek(): void
    {
        $weeks = $this->config->defaultScheduleWeeks();
        $week1Id = $weeks[0]['id'] ?? null;
        $maxSort = collect($weeks)->max('sort') ?? count($weeks) - 1;
        $newSort = $maxSort + 1;

        $weeks[] = [
            'id' => "default_{$newSort}",
            'linkedTo' => $week1Id,
            'slots' => [],
            'sort' => $newSort,
        ];

        $this->config->setDefaultScheduleWeeks($weeks);
    }

    protected function addUserWeek(): void
    {
        $defaultWeeks = $this->config->defaultScheduleWeeks();
        $userWeeks = $this->config->userScheduleWeeks($this->userId);

        $allWeeks = collect($defaultWeeks)->merge($userWeeks);
        $firstWeekId = $allWeeks->first()['id'] ?? null;
        $maxSort = $allWeeks->max('sort') ?? $allWeeks->count() - 1;
        $newSort = $maxSort + 1;

        $userWeeks[] = [
            'id' => "user_{$newSort}",
            'linkedTo' => $firstWeekId,
            'slots' => [],
            'sort' => $newSort,
        ];

        $this->config->setUserScheduleWeeks($this->userId, $userWeeks);
    }

    protected function removeWeek(string $weekId): void
    {
        if ($this->userId !== null) {
            $this->removeWeekForUser($weekId);

            return;
        }

        $weeks = $this->config->defaultScheduleWeeks();

        if ($this->getWeekIndex($weeks, $weekId) === 0) {
            return;
        }

        $allWeeks = $weeks;
        $weeks = collect($weeks)
            ->filter(fn (array $week) => $week['id'] !== $weekId)
            ->map(function (array $week) use ($weekId, $allWeeks) {
                if ($week['linkedTo'] === $weekId) {
                    $week['linkedTo'] = null;
                    $week['slots'] = $this->resolveLinkedSlotsRaw($week, $allWeeks);
                }

                return $week;
            })
            ->values()
            ->all();

        $this->config->setDefaultScheduleWeeks($weeks);
    }

    protected function removeWeekForUser(string $weekId): void
    {
        $defaultWeeks = $this->config->defaultScheduleWeeks();
        $userWeeks = $this->config->userScheduleWeeks($this->userId);

        if ($this->getWeekIndex($this->resolveSchedule($defaultWeeks, $userWeeks), $weekId) === 0) {
            return;
        }

        $defaultWeekIds = collect($defaultWeeks)->pluck('id')->all();
        $isUserAdded = ! in_array($weekId, $defaultWeekIds);

        if ($isUserAdded) {
            $userWeeks = array_values(array_filter($userWeeks, fn (array $w) => ($w['id'] ?? null) !== $weekId));
        } else {
            $this->setUserWeekOverride($userWeeks, $weekId, [
                'removed' => true,
            ]);
        }

        $this->config->setUserScheduleWeeks($this->userId, $userWeeks);
    }

    // --- Week linking ---

    protected function linkWeek(string $weekId, ?string $linkToWeekId): void
    {
        if ($this->userId !== null) {
            return;
        }

        $weeks = $this->config->defaultScheduleWeeks();

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

        $this->config->setDefaultScheduleWeeks($weeks);
    }

    protected function unlinkWeek(string $weekId): void
    {
        if ($this->userId !== null) {
            $this->unlinkWeekForUser($weekId);

            return;
        }

        $weeks = $this->config->defaultScheduleWeeks();

        foreach ($weeks as &$week) {
            if ($week['id'] === $weekId && $week['linkedTo'] !== null) {
                $resolvedSlots = $this->resolveLinkedSlotsRaw($week, $weeks);
                $week['slots'] = $this->filterNonEmptySlots($resolvedSlots);
                $week['linkedTo'] = null;
                break;
            }
        }

        $this->config->setDefaultScheduleWeeks($weeks);
    }

    protected function unlinkWeekForUser(string $weekId): void
    {
        $defaultWeeks = $this->config->defaultScheduleWeeks();
        $userWeeks = $this->config->userScheduleWeeks($this->userId);
        $schedule = $this->resolveSchedule($defaultWeeks, $userWeeks);

        $week = collect($schedule)->firstWhere('id', $weekId);
        if (! $week || $week['linkedTo'] === null) {
            return;
        }

        $resolvedSlots = $this->resolveLinkedSlotsRaw($week, $schedule);
        $copiedSlots = $this->filterNonEmptySlots($resolvedSlots);

        $defaultWeekIds = collect($defaultWeeks)->pluck('id')->all();
        $isUserAdded = ! in_array($weekId, $defaultWeekIds);

        if ($isUserAdded) {
            $index = $this->findUserWeekIndex($userWeeks, $weekId);
            if ($index !== null) {
                $userWeeks[$index]['linkedTo'] = null;
                $userWeeks[$index]['slots'] = $copiedSlots;
            }
        } else {
            $this->setUserWeekOverride($userWeeks, $weekId, [
                'linkedTo' => null,
                'slots' => $copiedSlots,
            ]);
        }

        $this->config->setUserScheduleWeeks($this->userId, $userWeeks);
    }

    protected function relinkWeek(string $weekId): void
    {
        if ($this->userId === null) {
            return;
        }

        $userWeeks = $this->config->userScheduleWeeks($this->userId);
        $index = $this->findUserWeekIndex($userWeeks, $weekId);

        if ($index !== null) {
            unset($userWeeks[$index]['linkedTo']);

            if (empty($userWeeks[$index]['slots']) && ! array_key_exists('linkedTo', $userWeeks[$index])) {
                $userWeeks = array_values(array_filter($userWeeks, fn (array $w) => ($w['id'] ?? null) !== $weekId));
            }
        }

        $this->config->setUserScheduleWeeks($this->userId, $userWeeks);
    }

    protected function resetToDefault(): void
    {
        if ($this->userId === null) {
            return;
        }

        $this->config->setUserScheduleWeeks($this->userId, []);
    }

    // --- Slot operations ---

    protected function assignProgram(string $weekId, int $day, int $slot, int $programId): void
    {
        $this->saveSlotChange($weekId, $day, $slot, $programId);
    }

    protected function moveProgram(string $weekId, int $fromDay, int $fromSlot, int $toDay, int $toSlot): void
    {
        $allWeeks = $this->getAllWeeks();
        $week = collect($allWeeks)->firstWhere('id', $weekId);

        if (! $week || $week['linkedTo'] !== null) {
            return;
        }

        $fromSlotData = $this->findSlot($week['slots'] ?? [], $fromDay, $fromSlot);
        $programId = $fromSlotData['programId'] ?? null;

        $this->saveSlotChange($weekId, $fromDay, $fromSlot, null);
        $this->saveSlotChange($weekId, $toDay, $toSlot, $programId);
    }

    protected function swapPrograms(string $week1Id, int $day1, int $slot1, string $week2Id, int $day2, int $slot2): void
    {
        $allWeeks = $this->getAllWeeks();
        $week1 = collect($allWeeks)->firstWhere('id', $week1Id);
        $week2 = collect($allWeeks)->firstWhere('id', $week2Id);

        if (! $week1 || ! $week2 || $week1['linkedTo'] !== null || $week2['linkedTo'] !== null) {
            return;
        }

        $slot1Data = $this->findSlot($week1['slots'] ?? [], $day1, $slot1);
        $slot2Data = $this->findSlot($week2['slots'] ?? [], $day2, $slot2);

        $this->saveSlotChange($week1Id, $day1, $slot1, $slot2Data['programId'] ?? null);
        $this->saveSlotChange($week2Id, $day2, $slot2, $slot1Data['programId'] ?? null);
    }

    protected function removeProgramFromSlots(int $programId): void
    {
        $weeks = $this->config->defaultScheduleWeeks();

        foreach ($weeks as &$week) {
            if ($week['linkedTo'] !== null) {
                continue;
            }
            $week['slots'] = array_values(array_filter(
                $week['slots'] ?? [],
                fn (array $s) => ($s['programId'] ?? null) !== $programId
            ));
        }

        $this->config->setDefaultScheduleWeeks($weeks);
    }

    protected function saveSlotChange(string $weekId, int $day, int $slot, ?int $programId): void
    {
        if ($this->userId === null) {
            $weeks = $this->config->defaultScheduleWeeks();
            foreach ($weeks as &$week) {
                if ($week['id'] === $weekId) {
                    $this->setSlot($week['slots'], $day, $slot, $programId);
                    break;
                }
            }
            $this->config->setDefaultScheduleWeeks($weeks);

            return;
        }

        $defaultWeeks = $this->config->defaultScheduleWeeks();
        $userWeeks = $this->config->userScheduleWeeks($this->userId);
        $defaultWeekIds = collect($defaultWeeks)->pluck('id')->all();
        $isUserAdded = ! in_array($weekId, $defaultWeekIds);

        if ($isUserAdded) {
            $index = $this->findUserWeekIndex($userWeeks, $weekId);
            if ($index !== null) {
                $this->setSlot($userWeeks[$index]['slots'], $day, $slot, $programId);
            }
        } else {
            $index = $this->findUserWeekIndex($userWeeks, $weekId);

            if ($index === null) {
                $userWeeks[] = ['id' => $weekId, 'slots' => []];
                $index = count($userWeeks) - 1;
            }

            $hasLinkedToOverride = array_key_exists('linkedTo', $userWeeks[$index]);

            if ($hasLinkedToOverride) {
                $this->setSlot($userWeeks[$index]['slots'], $day, $slot, $programId);
            } else {
                $defaultSlot = $this->getDefaultSlotForWeek($defaultWeeks, $weekId, $day, $slot);
                $defaultProgramId = $defaultSlot['programId'] ?? null;

                if ($programId === $defaultProgramId) {
                    $this->removeSlot($userWeeks[$index]['slots'], $day, $slot);
                } else {
                    $this->removeSlot($userWeeks[$index]['slots'], $day, $slot);
                    $userWeeks[$index]['slots'][] = ['day' => $day, 'slot' => $slot, 'programId' => $programId];
                }
            }

            if (empty($userWeeks[$index]['slots']) && ! $hasLinkedToOverride) {
                $userWeeks = array_values(array_filter($userWeeks, fn (array $w) => ($w['id'] ?? null) !== $weekId));
            }
        }

        $this->config->setUserScheduleWeeks($this->userId, $userWeeks);
    }

    // --- Helpers ---

    protected function getAllWeeks(): array
    {
        $defaultWeeks = $this->config->defaultScheduleWeeks();
        $userWeeks = $this->userId !== null ? $this->config->userScheduleWeeks($this->userId) : [];

        return $this->resolveSchedule($defaultWeeks, $userWeeks);
    }

    protected function resolveSchedule(array $defaultWeeks, array $userOverrides): array
    {
        if (empty($userOverrides)) {
            return $defaultWeeks;
        }

        $defaultWeekIds = collect($defaultWeeks)->pluck('id')->all();

        $weeks = collect($defaultWeeks)->map(function (array $week) use ($userOverrides) {
            $override = collect($userOverrides)->firstWhere('id', $week['id']);

            if (! $override) {
                return $week;
            }

            if (! empty($override['removed'])) {
                return null;
            }

            if (array_key_exists('linkedTo', $override)) {
                $week['linkedTo'] = $override['linkedTo'];
            }

            if (! empty($override['slots'])) {
                $week['slots'] = $this->mergeSlotOverrides($week['slots'] ?? [], $override['slots']);
            }

            return $week;
        })->filter();

        $userAddedWeeks = collect($userOverrides)
            ->filter(fn (array $override) => ! in_array($override['id'] ?? null, $defaultWeekIds));

        return $weeks->merge($userAddedWeeks)->sortBy('sort')->values()->all();
    }

    protected function mergeSlotOverrides(array $baseSlots, array $overrideSlots): array
    {
        $result = $baseSlots;

        foreach ($overrideSlots as $override) {
            $result = array_filter($result, fn (array $s) => ! ($s['day'] === $override['day'] && $s['slot'] === $override['slot']));

            if (($override['programId'] ?? null) !== null) {
                $result[] = $override;
            }
        }

        return array_values($result);
    }

    protected function resolveLinkedSlotsRaw(array $week, array $allWeeks): array
    {
        if ($week['linkedTo'] === null) {
            return $week['slots'] ?? [];
        }

        $sourceWeek = collect($allWeeks)->firstWhere('id', $week['linkedTo']);

        return $sourceWeek ? $this->resolveLinkedSlotsRaw($sourceWeek, $allWeeks) : ($week['slots'] ?? []);
    }

    protected function filterNonEmptySlots(array $slots): array
    {
        return array_values(array_filter($slots, fn (array $slot) => ($slot['programId'] ?? null) !== null));
    }

    protected function getDefaultSlotForWeek(array $defaultWeeks, string $weekId, int $day, int $slot): ?array
    {
        $week = collect($defaultWeeks)->firstWhere('id', $weekId);

        if (! $week) {
            return null;
        }

        if ($week['linkedTo'] !== null) {
            return $this->getDefaultSlotForWeek($defaultWeeks, $week['linkedTo'], $day, $slot);
        }

        return $this->findSlot($week['slots'] ?? [], $day, $slot);
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

    protected function setSlot(array &$slots, int $day, int $slot, ?int $programId): void
    {
        $this->removeSlot($slots, $day, $slot);

        if ($programId !== null) {
            $slots[] = ['day' => $day, 'slot' => $slot, 'programId' => $programId];
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

    protected function findUserWeekIndex(array $userWeeks, string $weekId): ?int
    {
        foreach ($userWeeks as $index => $week) {
            if (($week['id'] ?? null) === $weekId) {
                return $index;
            }
        }

        return null;
    }

    protected function setUserWeekOverride(array &$userWeeks, string $weekId, array $data): void
    {
        $data['id'] = $weekId;
        $index = $this->findUserWeekIndex($userWeeks, $weekId);

        if ($index !== null) {
            $userWeeks[$index] = array_merge($userWeeks[$index], $data);
        } else {
            $userWeeks[] = $data;
        }
    }

    public static function isProgramUsedInConfig(TrainingPlan $trainingPlan, int $programId, array $userIds): bool
    {
        $config = TrainingPlanConfig::from($trainingPlan->config->all());

        foreach ($config->defaultScheduleWeeks() as $week) {
            foreach ($week['slots'] ?? [] as $slot) {
                if (($slot['programId'] ?? null) === $programId) {
                    return true;
                }
            }
        }

        foreach ($userIds as $userId) {
            foreach ($config->userScheduleWeeks($userId) as $week) {
                foreach ($week['slots'] ?? [] as $slot) {
                    if (($slot['programId'] ?? null) === $programId) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    protected function save(): void
    {
        $this->trainingPlan->config = $this->config->toArray();
        $this->trainingPlan->save();
    }
}
