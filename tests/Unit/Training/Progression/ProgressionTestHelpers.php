<?php

namespace Tests\Unit\Training\Progression;

use App\Models\Training\Progression\Athlete\AthleteData;
use App\Models\Training\Progression\Athlete\AthleteTest;
use App\Models\Training\Progression\Config\ProgressionConfig;
use App\Models\Training\Progression\Override\OverrideStore;

trait ProgressionTestHelpers
{
    protected function createDefaultConfig(): ProgressionConfig
    {
        return new ProgressionConfig(
            weightStrategy: 'fixed_step',
            repStrategy: 'paired_ladder',
            targetImprovement: 0.125,
            startingReps: 14,
            stepDownInterval: 2,
            repDecrement: 2,
            minimumReps: 6,
            incrementStep: 0.5,
            blockLength: 5,
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
