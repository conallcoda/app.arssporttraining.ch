<?php

namespace Tests\Unit\Training\Progression;

use App\Models\Training\Progression\Athlete\AthleteData;
use App\Models\Training\Progression\Athlete\AthleteTest;
use App\Models\Training\Progression\Config\ProgressionConfig;
use App\Models\Training\Progression\Config\ResolvedExerciseConfig;
use App\Models\Training\Progression\Override\OverrideStore;
use App\Models\Training\Progression\Strategy\Rep\PairedLadderRepConfig;
use App\Models\Training\Progression\Strategy\Weight\CompoundedWeightConfig;
use App\Models\Training\Progression\Strategy\Weight\FixedStepWeightConfig;

trait ProgressionTestHelpers
{
    protected function createDefaultConfig(): ProgressionConfig
    {
        return new ProgressionConfig(
            blockLength: 5,
            weightConfig: new FixedStepWeightConfig(
                targetImprovement: 12.5,
                incrementStep: 0.5,
            ),
            repConfig: new PairedLadderRepConfig(
                startingReps: 14,
                stepDownInterval: 2,
                repDecrement: 2,
                minimumReps: 6,
            ),
        );
    }

    protected function createDefaultResolvedConfig(int $exerciseId = 1, int $startingReps = 14): ResolvedExerciseConfig
    {
        return new ResolvedExerciseConfig(
            exerciseId: $exerciseId,
            blockLength: 5,
            weightConfig: new FixedStepWeightConfig(
                targetImprovement: 12.5,
                incrementStep: 0.5,
            ),
            repConfig: new PairedLadderRepConfig(
                startingReps: $startingReps,
                stepDownInterval: 2,
                repDecrement: 2,
                minimumReps: 6,
            ),
            modifier: 1.0,
        );
    }

    protected function createCompoundedResolvedConfig(int $exerciseId = 1, int $startingReps = 14): ResolvedExerciseConfig
    {
        return new ResolvedExerciseConfig(
            exerciseId: $exerciseId,
            blockLength: 5,
            weightConfig: new CompoundedWeightConfig(
                targetImprovement: 12.5,
                incrementStep: 0.5,
            ),
            repConfig: new PairedLadderRepConfig(
                startingReps: $startingReps,
                stepDownInterval: 2,
                repDecrement: 2,
                minimumReps: 6,
            ),
            modifier: 1.0,
        );
    }

    protected function createDefaultAthleteData(): AthleteData
    {
        return new AthleteData(
            athleteId: 1,
            tests: [
                1 => new AthleteTest(
                    exerciseId: 1,
                    reps: 8,
                    weight: 56.0,
                ),
            ],
        );
    }

    protected function createDefaultOverrideStore(): OverrideStore
    {
        return new OverrideStore;
    }
}
