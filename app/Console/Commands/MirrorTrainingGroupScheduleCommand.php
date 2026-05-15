<?php

namespace App\Console\Commands;

use App\Training\TrainingGroupScheduleMirrorService;
use Illuminate\Console\Command;

class MirrorTrainingGroupScheduleCommand extends Command
{
    public const SIGNATURE = 'data:mirror-training-group-schedule
        {sourceGroupId : Source group id}
        {targetGroupId : Target group id}
        {--replace : Clear the target group schedule before mirroring}';

    protected $signature = self::SIGNATURE;

    protected $description = 'Copies scheduled programs, blocks, and slots from one group into another local sandbox group.';

    public function handle(TrainingGroupScheduleMirrorService $service): int
    {
        $result = $service->mirror(
            sourceGroupId: (int) $this->argument('sourceGroupId'),
            targetGroupId: (int) $this->argument('targetGroupId'),
            replace: (bool) $this->option('replace'),
        );

        $this->info('Training group schedule mirrored.');
        $this->line('Programs mirrored: '.$result['mirrored_programs']);
        $this->line('Blocks mirrored: '.$result['mirrored_blocks']);
        $this->line('Slots mirrored: '.$result['mirrored_slots']);

        return self::SUCCESS;
    }
}
