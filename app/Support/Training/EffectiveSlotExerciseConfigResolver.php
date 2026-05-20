<?php

namespace App\Support\Training;

use App\Models\Exercise\Exercise;
use App\Models\Training\TrainingProgramSlotExercise;

class EffectiveSlotExerciseConfigResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(TrainingProgramSlotExercise $slotExercise): array
    {
        $slotExercise->loadMissing([
            'exercise',
            'slot.trainingProgram.program.exercises',
        ]);

        $baseConfig = $slotExercise->exercise?->config;
        $baseConfigArray = is_object($baseConfig) && method_exists($baseConfig, 'toArray')
            ? $baseConfig->toArray()
            : [];

        $slot = $slotExercise->slot;
        $program = $slot?->trainingProgram?->program;
        $programConfig = $program?->config;

        if ($slot === null || $program === null || ! is_object($programConfig) || ! method_exists($programConfig, 'resolveExercise')) {
            return $baseConfigArray;
        }

        $sourceExercise = $program->exercises
            ->first(fn (Exercise $exercise): bool => $this->exerciseSignature(
                exerciseId: (int) $exercise->id,
                sort: (int) ($exercise->pivot->sort ?? 0),
                group: $exercise->pivot->group,
                type: (string) ($exercise->pivot->type ?? 'main'),
            ) === $this->exerciseSignature(
                exerciseId: (int) ($slotExercise->exercise_id ?? 0),
                sort: (int) $slotExercise->sort,
                group: $slotExercise->group,
                type: (string) ($slotExercise->type ?? 'main'),
            ));

        if (! $sourceExercise instanceof Exercise) {
            return $baseConfigArray;
        }

        $programExerciseId = (int) ($sourceExercise->pivot->id ?? 0);

        if ($programExerciseId <= 0) {
            return $baseConfigArray;
        }

        return $programConfig
            ->resolveExercise($sourceExercise->config, $programExerciseId, $slot->user_id)
            ->effectiveConfig;
    }

    private function exerciseSignature(int $exerciseId, int $sort, ?string $group, string $type): string
    {
        return implode(':', [$exerciseId, $sort, $group ?? '', $type]);
    }
}
