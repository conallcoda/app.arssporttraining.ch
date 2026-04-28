<?php

namespace App\Jobs;

use App\Training\TrainingSessionRebuildService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RebuildFutureSlotsForAthleteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $userId,
        public ?string $fromDate = null,
    ) {}

    public function handle(TrainingSessionRebuildService $rebuildService): void
    {
        $rebuildService->rebuildFutureSlotsForAthlete($this->userId, $this->fromDate);
    }
}
