<?php

namespace App\Support\Training;

use App\Models\Training\TrainingProgramSlotStatusEnum;

class SlotStatusPresenter
{
    /**
     * @return array{light: string, dark: string}
     */
    public function color(TrainingProgramSlotStatusEnum|string|null $status): array
    {
        $value = $status instanceof TrainingProgramSlotStatusEnum
            ? $status->value
            : $status;

        $enum = TrainingProgramSlotStatusEnum::tryFrom($value ?? TrainingProgramSlotStatusEnum::Pending->value)
            ?? TrainingProgramSlotStatusEnum::Pending;

        return $enum->barColor();
    }

    /**
     * @param  array{completed:int, partial:int, skipped:int, pending:int}  $counts
     */
    public function aggregateStatus(array $counts): string
    {
        $total = array_sum($counts);

        if ($total === 0 || $counts['pending'] === $total) {
            return 'pending';
        }

        if ($counts['skipped'] === $total) {
            return 'skipped';
        }

        if ($counts['partial'] > 0) {
            return 'partially_completed';
        }

        if ($counts['pending'] === 0 && ($counts['completed'] + $counts['skipped']) === $total && $counts['completed'] > 0) {
            return 'completed';
        }

        return 'partially_completed';
    }

    /**
     * @param  array<int, TrainingProgramSlotStatusEnum|string|null>  $statuses
     * @return array{light: string, dark: string}
     */
    public function aggregateColor(array $statuses): array
    {
        $counts = [
            'completed' => 0,
            'partial' => 0,
            'skipped' => 0,
            'pending' => 0,
        ];

        foreach ($statuses as $status) {
            $value = $status instanceof TrainingProgramSlotStatusEnum
                ? $status->value
                : ($status ?? TrainingProgramSlotStatusEnum::Pending->value);

            match ($value) {
                TrainingProgramSlotStatusEnum::Completed->value => $counts['completed']++,
                TrainingProgramSlotStatusEnum::PartiallyCompleted->value => $counts['partial']++,
                TrainingProgramSlotStatusEnum::Skipped->value => $counts['skipped']++,
                default => $counts['pending']++,
            };
        }

        return $this->color($this->aggregateStatus($counts));
    }
}
