<?php

namespace App\Console\Commands;

use App\Training\AutomaticOneRepMaxSessionCountRepair;
use Illuminate\Console\Command;

class RepairAutomaticOneRepMaxSessionCountsCommand extends Command
{
    protected $signature = 'training:repair-automatic-1rm-session-counts
        {--apply : Apply the repair; without this option the command only previews changes}
        {--training-program=* : Limit the scan to calendar training program ids}
        {--block=* : Limit the scan to category block ids}
        {--compiled-since=2026-08-27 : Limit repair to sessions compiled since the regression was introduced}';

    protected $description = 'Preview or selectively recompile automatic-1RM exercises affected by the grouped session-count bug';

    public function handle(AutomaticOneRepMaxSessionCountRepair $repair): int
    {
        $trainingProgramIds = $this->positiveIds($this->option('training-program'));
        $blockIds = $this->positiveIds($this->option('block'));
        $compiledSince = $this->option('compiled-since');

        if (! is_string($compiledSince) || strtotime($compiledSince) === false) {
            $this->error('Invalid --compiled-since date.');

            return self::FAILURE;
        }

        $candidates = $repair->scan($trainingProgramIds, $blockIds, $compiledSince);

        if ($candidates->isEmpty()) {
            $this->info('No affected automatic-1RM exercise sessions were found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Group', 'Athlete', 'Program', 'Block', 'Date', 'Exercise', 'Old total', 'Correct total', 'Action'],
            $candidates->map(fn (array $row): array => [
                $row['group'],
                $row['athlete'],
                $row['training_program_id'],
                $row['block_id'] ?? '-',
                $row['slot_date'],
                $row['exercise'].' (#'.$row['program_exercise_id'].')',
                $row['old_session_count'],
                $row['new_session_count'],
                $row['recorded'] ? 'preserve recorded' : ($this->option('apply') ? 'recompile' : 'would recompile'),
            ])->all(),
        );

        $recorded = $candidates->where('recorded', true)->count();
        $repairable = $candidates->count() - $recorded;

        if (! $this->option('apply')) {
            $this->warn("Preview only: {$repairable} exercise sessions would be recompiled; {$recorded} recorded sessions would be preserved.");
            $this->line('Run the same command with --apply after reviewing this list.');

            return self::SUCCESS;
        }

        $result = $repair->apply($candidates);
        $this->info("Recompiled {$result['recompiled']} affected automatic-1RM exercise sessions.");
        $this->info("Preserved {$result['preserved']} recorded exercise sessions; {$result['missing']} rows were no longer available.");

        return self::SUCCESS;
    }

    /** @return list<int> */
    private function positiveIds(array $values): array
    {
        return collect($values)
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
    }
}
