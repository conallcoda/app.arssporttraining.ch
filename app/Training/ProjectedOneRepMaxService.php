<?php

namespace App\Training;

use App\Data\Athlete\Metric\MetricEnum;
use App\Models\Athlete\MetricSubmission;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Users\UserGroup;
use App\Training\Reference\OneRepMaxConversion;

class ProjectedOneRepMaxService
{
    public function syncForBlock(TrainingProgramBlock $block): void
    {
        if (! $block->config?->autoRecord1rm || $block->config->goal === null || $block->end === null) {
            $this->removeForBlock($block);

            return;
        }

        $athleteIds = $this->resolveAthleteIds($block);

        foreach ($athleteIds as $athleteId) {
            $this->syncForAthlete($block, $athleteId);
        }

        MetricSubmission::forBlock($block->id)
            ->forMetric(MetricEnum::OneRepMax)
            ->whereNotIn('user_id', $athleteIds)
            ->delete();
    }

    public function syncForAthlete(TrainingProgramBlock $block, int $userId): void
    {
        $base1rm = $this->findBase1rm($userId, $block->start);

        if ($base1rm === null) {
            $this->removeForBlockAndUser($block, $userId);

            return;
        }

        $projected = OneRepMaxConversion::targetOneRepMax($base1rm, $block->config->goal);

        $submission = MetricSubmission::updateOrCreate(
            [
                'owner_type' => TrainingProgramBlock::class,
                'owner_id' => $block->id,
                'user_id' => $userId,
                'metric' => MetricEnum::OneRepMax,
            ],
            [
                'recorded_at' => $block->end,
                'recorded_by' => auth()->id(),
            ]
        );

        $submission->values()->delete();

        $values = [
            'measuredReps' => '1',
            'measuredWeight' => (string) $projected,
            'estimated1RM' => (string) $projected,
            'goalPercent' => (string) $block->config->goal,
        ];

        foreach ($values as $field => $value) {
            $submission->values()->create([
                'field' => $field,
                'value' => $value,
            ]);
        }
    }

    public function syncForAthleteBlocks(int $userId): void
    {
        $groupIds = UserGroup::whereHas('members', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->pluck('id');

        $blocks = TrainingProgramBlock::query()
            ->where('active', true)
            ->whereNotNull('end')
            ->where(function ($query) use ($userId, $groupIds) {
                $query->where(function ($q) use ($groupIds) {
                    $q->whereIn('group_id', $groupIds)->whereNull('user_id')->whereNull('parent_id');
                })->orWhere('user_id', $userId);
            })
            ->get()
            ->filter(fn (TrainingProgramBlock $block) => $block->config?->autoRecord1rm && $block->config->goal !== null);

        foreach ($blocks as $block) {
            if ($block->user_id === null) {
                $override = $block->athleteOverride($userId);
                if ($override && $override->active) {
                    continue;
                }
            }

            $this->syncForAthlete($block, $userId);
        }
    }

    public function removeForBlock(TrainingProgramBlock $block): void
    {
        MetricSubmission::forBlock($block->id)->delete();
    }

    public function removeForBlockAndUser(TrainingProgramBlock $block, int $userId): void
    {
        MetricSubmission::forBlock($block->id)
            ->forAthlete($userId)
            ->delete();
    }

    /** @return list<int> */
    private function resolveAthleteIds(TrainingProgramBlock $block): array
    {
        if ($block->user_id !== null) {
            return [$block->user_id];
        }

        $group = UserGroup::with('members')->find($block->group_id);
        if ($group === null) {
            return [];
        }

        $memberIds = $group->members->pluck('id')->all();

        $activeOverrideUserIds = TrainingProgramBlock::query()
            ->where('parent_id', $block->id)
            ->where('active', true)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();

        return array_values(array_diff($memberIds, $activeOverrideUserIds));
    }

    private function findBase1rm(int $userId, $blockStart): ?float
    {
        $submission = MetricSubmission::forAthlete($userId)
            ->forMetric(MetricEnum::OneRepMax)
            ->where('recorded_at', '<=', $blockStart)
            ->latest('recorded_at')
            ->with('values')
            ->first();

        if ($submission === null) {
            return null;
        }

        $estimated = $submission->getFieldValue('estimated1RM');

        return $estimated !== null ? (float) $estimated : null;
    }
}
