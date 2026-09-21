<?php

namespace App\Training;

use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramSlot;
use Illuminate\Database\Eloquent\Builder;

class TrainingPlanSessionCountResolver
{
    public function resolveForSlot(
        TrainingProgramSlot $slot,
        ?TrainingProgramBlock $block = null,
    ): int {
        $slot->loadMissing('trainingProgram');

        $query = TrainingProgramSlot::query()
            ->where('training_program_id', $slot->training_program_id)
            ->whereNull('cancelled_at');

        if ($block instanceof TrainingProgramBlock) {
            $query->whereBetween('datetime', [
                $block->start->copy()->startOfDay(),
                ($block->end ?? $block->start)->copy()->endOfDay(),
            ]);
        }

        return $this->resolve($slot->trainingProgram, $block, $query);
    }

    public function resolve(
        TrainingProgram $program,
        ?TrainingProgramBlock $block,
        Builder $scopedSlots,
        int $knownScheduledCount = 0,
    ): int {
        return max(
            1,
            $this->configuredCount($program, $block),
            $knownScheduledCount,
            $this->highestAthleteScheduledCount($scopedSlots),
        );
    }

    public function configuredCount(TrainingProgram $program, ?TrainingProgramBlock $block): int
    {
        if (! $block instanceof TrainingProgramBlock) {
            return (int) ($program->planned_session_count ?? 0);
        }

        return (int) ($block->config?->plannedSessionCounts[$program->id] ?? 0);
    }

    public function highestAthleteScheduledCount(Builder $scopedSlots): int
    {
        return (int) ((clone $scopedSlots)
            ->selectRaw('user_id, COUNT(*) as session_count')
            ->groupBy('user_id')
            ->pluck('session_count')
            ->max() ?? 0);
    }
}
