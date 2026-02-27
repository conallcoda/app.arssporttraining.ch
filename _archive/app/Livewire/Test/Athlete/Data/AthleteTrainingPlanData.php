<?php

namespace App\Livewire\Test\Athlete\Data;

use App\Models\ProgramCategory;
use App\Models\TrainingPlan;
use Coda\Cms\Data\AbstractData;

class AthleteTrainingPlanData extends AbstractData
{
    /**
     * @param  array<int, array{id: int, name: string, color: ?string}>  $categories
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $startDate,
        public int $totalWeeks,
        public array $categories = [],
    ) {}

    public static function fromModel(TrainingPlan $plan, int $userId): self
    {
        $config = $plan->config;

        $startDate = $config->userScheduleStartDate($userId);

        $weeks = $config->isUserScheduleLocked($userId)
            ? $config->defaultScheduleWeeks()
            : $config->userScheduleWeeks($userId);

        $programIds = self::extractProgramIdsFromWeeks($weeks);

        $categories = $plan->programs
            ->whereIn('id', $programIds)
            ->pluck('programCategory')
            ->filter()
            ->unique('id')
            ->sortBy('sort')
            ->map(fn (ProgramCategory $cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'color' => $cat->color,
            ])
            ->values()
            ->all();

        return new self(
            id: $plan->id,
            name: $plan->name,
            startDate: $startDate,
            totalWeeks: count($weeks),
            categories: $categories,
        );
    }

    /** @return int[] */
    protected static function extractProgramIdsFromWeeks(array $weeks): array
    {
        $programIds = [];

        foreach ($weeks as $week) {
            $slots = self::getResolvedSlotsForWeek($week, $weeks);

            foreach ($slots as $slot) {
                foreach ($slot['programs'] ?? [] as $programId) {
                    if (! in_array($programId, $programIds)) {
                        $programIds[] = $programId;
                    }
                }
            }
        }

        return $programIds;
    }

    protected static function getResolvedSlotsForWeek(array $week, array $allWeeks): array
    {
        if (($week['linkedTo'] ?? null) === null) {
            return $week['slots'] ?? [];
        }

        $sourceWeek = collect($allWeeks)->firstWhere('id', $week['linkedTo']);

        return $sourceWeek ? self::getResolvedSlotsForWeek($sourceWeek, $allWeeks) : ($week['slots'] ?? []);
    }
}
