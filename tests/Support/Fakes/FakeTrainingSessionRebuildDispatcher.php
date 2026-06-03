<?php

namespace Tests\Support\Fakes;

use App\Training\TrainingSessionRebuildDispatcher;

class FakeTrainingSessionRebuildDispatcher extends TrainingSessionRebuildDispatcher
{
    /**
     * @var list<array{method: string, exerciseProgramId?: int, userId?: int, fromDate?: string|null}>
     */
    public array $calls = [];

    public function dispatchFutureSlotsForExerciseProgramChange(int $exerciseProgramId, ?int $userId = null, ?string $fromDate = null): void
    {
        $this->calls[] = [
            'method' => __FUNCTION__,
            'exerciseProgramId' => $exerciseProgramId,
            'userId' => $userId,
            'fromDate' => $fromDate,
        ];
    }

    public function dispatchFutureSlotsForExerciseProgram(int $exerciseProgramId, ?string $fromDate = null): void
    {
        $this->calls[] = [
            'method' => __FUNCTION__,
            'exerciseProgramId' => $exerciseProgramId,
            'fromDate' => $fromDate,
        ];
    }

    public function dispatchFutureSlotsForAthleteExerciseProgram(int $userId, int $exerciseProgramId, ?string $fromDate = null): void
    {
        $this->calls[] = [
            'method' => __FUNCTION__,
            'exerciseProgramId' => $exerciseProgramId,
            'userId' => $userId,
            'fromDate' => $fromDate,
        ];
    }

    public function dispatchFutureSlotsForTrainingProgramChange(int $trainingProgramId, ?int $userId = null, ?string $fromDate = null): void
    {
        $this->calls[] = [
            'method' => __FUNCTION__,
            'trainingProgramId' => $trainingProgramId,
            'userId' => $userId,
            'fromDate' => $fromDate,
        ];
    }

    public function dispatchFutureSlotsForAthlete(int $userId, ?string $fromDate = null): void
    {
        $this->calls[] = [
            'method' => __FUNCTION__,
            'userId' => $userId,
            'fromDate' => $fromDate,
        ];
    }
}
