<?php

namespace App\Support\Training;

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
            'programExercise.exercise',
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

        $sourceProgramExercise = $slotExercise->programExercise;
        $sourceExercise = $sourceProgramExercise?->exercise;
        $programExerciseId = (int) ($slotExercise->exercise_program_exercise_id ?? 0);

        if ($sourceExercise === null || $programExerciseId <= 0) {
            return $baseConfigArray;
        }

        return $programConfig
            ->resolveExercise($sourceExercise->config, $programExerciseId, $slot->user_id)
            ->effectiveConfig;
    }
}
