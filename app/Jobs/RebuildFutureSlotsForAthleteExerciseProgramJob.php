<?php

namespace App\Jobs;

use App\Training\TrainingSessionRebuildService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RebuildFutureSlotsForAthleteExerciseProgramJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $userId,
        public int $exerciseProgramId,
    ) {}

    public function handle(TrainingSessionRebuildService $rebuildService): void
    {
        $rebuildService->rebuildFutureSlotsForAthleteExerciseProgram($this->userId, $this->exerciseProgramId);
    }
}
