<?php

namespace Tests\Support\Fakes;

use App\Training\TrainingSessionRebuildDispatcher;

class FakeTrainingSessionRebuildDispatcher extends TrainingSessionRebuildDispatcher
{
    /**
     * @var list<array{method: string, exerciseProgramId?: int, userId?: int, fromDate?: string|null}>
     */
    public array $calls = [];

    public function dispatchFutureSlotsForExerciseProgramChange(int $exerciseProgramId, ?int $userId = null): void
    {
        $this->calls[] = [
            'method' => __FUNCTION__,
            'exerciseProgramId' => $exerciseProgramId,
            'userId' => $userId,
        ];
    }

    public function dispatchFutureSlotsForExerciseProgram(int $exerciseProgramId): void
    {
        $this->calls[] = [
            'method' => __FUNCTION__,
            'exerciseProgramId' => $exerciseProgramId,
        ];
    }

    public function dispatchFutureSlotsForAthleteExerciseProgram(int $userId, int $exerciseProgramId): void
    {
        $this->calls[] = [
            'method' => __FUNCTION__,
            'exerciseProgramId' => $exerciseProgramId,
            'userId' => $userId,
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
