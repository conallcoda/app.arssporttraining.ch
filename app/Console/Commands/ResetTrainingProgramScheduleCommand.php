<?php

namespace App\Console\Commands;

use App\Data\Training\Config\ExerciseOverrides;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetTrainingProgramScheduleCommand extends Command
{
    public const SIGNATURE = 'training:reset-program-schedule
        {trainingProgramId=61 : Training program id to reset}
        {--block=63 : Training program block id to offset}
        {--first-session=2026-06-14 : New date for the first scheduled slot}
        {--dry-run : Show what would change without writing}';

    protected $signature = self::SIGNATURE;

    protected $description = 'Moves a scheduled training program into the future and clears compiled slot/historical noise.';

    public function handle(): int
    {
        $trainingProgramId = (int) $this->argument('trainingProgramId');
        $blockId = (int) $this->option('block');
        $firstSessionDate = CarbonImmutable::parse((string) $this->option('first-session'))->startOfDay();
        $dryRun = (bool) $this->option('dry-run');

        $trainingProgram = TrainingProgram::query()
            ->with('program')
            ->find($trainingProgramId);

        if (! $trainingProgram instanceof TrainingProgram) {
            $this->error("Training program {$trainingProgramId} was not found.");

            return self::FAILURE;
        }

        $firstSlotDate = TrainingProgramSlot::query()
            ->where('training_program_id', $trainingProgramId)
            ->min('scheduled_date');

        if ($firstSlotDate === null) {
            $this->error("Training program {$trainingProgramId} has no scheduled slots to offset.");

            return self::FAILURE;
        }

        $lastSlotDate = TrainingProgramSlot::query()
            ->where('training_program_id', $trainingProgramId)
            ->max('scheduled_date');

        $offsetDays = (int) CarbonImmutable::parse($firstSlotDate)->startOfDay()->diffInDays($firstSessionDate, false);
        $lastSessionDate = CarbonImmutable::parse($lastSlotDate)->startOfDay()->addDays($offsetDays);

        $reset = function () use ($trainingProgram, $trainingProgramId, $blockId, $offsetDays, $firstSessionDate, $lastSessionDate, $dryRun): array {
            $blockSummary = $this->offsetBlock($blockId, $offsetDays, $firstSessionDate, $lastSessionDate, $dryRun);
            $slotSummary = $this->resetAndOffsetSlots($trainingProgramId, $offsetDays, $dryRun);
            $historicalOverridesCleared = $this->clearHistoricalOverrides($trainingProgram->program, $dryRun);

            return [
                ...$blockSummary,
                ...$slotSummary,
                'historical_overrides_cleared' => $historicalOverridesCleared,
            ];
        };

        $summary = $dryRun ? $reset() : DB::transaction($reset);

        $this->info($dryRun ? 'Dry run complete.' : 'Training program schedule reset.');
        $this->line("Training program: {$trainingProgramId}");
        $this->line("Block: {$blockId}");
        $this->line("Offset days: {$offsetDays}");
        $this->line("Block start: {$summary['block_start_before']} -> {$summary['block_start_after']}");
        $this->line("Block end: {$summary['block_end_before']} -> {$summary['block_end_after']}");
        $this->line("Slots reset/offset: {$summary['slots_reset']}");
        $this->line("Compiled slot exercises removed: {$summary['compiled_exercises_removed']}");
        $this->line("Historical override buckets cleared: {$summary['historical_overrides_cleared']}");

        return self::SUCCESS;
    }

    /** @return array{block_start_before: ?string, block_start_after: ?string, block_end_before: ?string, block_end_after: ?string} */
    private function offsetBlock(
        int $blockId,
        int $offsetDays,
        CarbonImmutable $firstSessionDate,
        CarbonImmutable $lastSessionDate,
        bool $dryRun,
    ): array {
        $block = TrainingProgramBlock::query()->find($blockId);

        if (! $block instanceof TrainingProgramBlock) {
            return [
                'block_start_before' => null,
                'block_start_after' => null,
                'block_end_before' => null,
                'block_end_after' => null,
            ];
        }

        $startBefore = $block->start?->toDateString();
        $endBefore = $block->end?->toDateString();

        $block->start = $block->start?->copy()->addDays($offsetDays);
        $block->end = $block->end?->copy()->addDays($offsetDays);

        if ($block->start === null || $block->start->gt($firstSessionDate)) {
            $block->start = $firstSessionDate;
        }

        if ($block->end === null || $block->end->lt($lastSessionDate)) {
            $block->end = $lastSessionDate;
        }

        if (! $dryRun) {
            $block->saveQuietly();
        }

        return [
            'block_start_before' => $startBefore,
            'block_start_after' => $block->start?->toDateString(),
            'block_end_before' => $endBefore,
            'block_end_after' => $block->end?->toDateString(),
        ];
    }

    /** @return array{slots_reset: int, compiled_exercises_removed: int} */
    private function resetAndOffsetSlots(int $trainingProgramId, int $offsetDays, bool $dryRun): array
    {
        $slotsReset = 0;
        $compiledExercisesRemoved = 0;

        $direction = $offsetDays >= 0 ? 'desc' : 'asc';

        TrainingProgramSlot::query()
            ->where('training_program_id', $trainingProgramId)
            ->withCount('exercises')
            ->orderBy('datetime', $direction)
            ->orderBy('id', $direction)
            ->get()
            ->each(function (TrainingProgramSlot $slot) use ($offsetDays, $dryRun, &$slotsReset, &$compiledExercisesRemoved): void {
                $slotsReset++;
                $compiledExercisesRemoved += (int) $slot->exercises_count;

                if ($dryRun) {
                    return;
                }

                $slot->exercises()->delete();

                $slot->forceFill([
                    'datetime' => $slot->datetime->copy()->addDays($offsetDays),
                    'scheduled_date' => $slot->scheduled_date?->copy()->addDays($offsetDays),
                    'status' => TrainingProgramSlotStatusEnum::Pending,
                    'compiled_at' => null,
                    'compiled_version' => null,
                    'exercise_count' => 0,
                    'completed_exercise_count' => 0,
                    'partial_exercise_count' => 0,
                    'skipped_exercise_count' => 0,
                    'pending_exercise_count' => 0,
                    'has_any_modification' => false,
                    'completed_at' => null,
                    'cancelled_at' => null,
                ])->saveQuietly();
            });

        return [
            'slots_reset' => $slotsReset,
            'compiled_exercises_removed' => $compiledExercisesRemoved,
        ];
    }

    private function clearHistoricalOverrides(?ExerciseProgram $exerciseProgram, bool $dryRun): int
    {
        if (! $exerciseProgram instanceof ExerciseProgram) {
            return 0;
        }

        $config = $exerciseProgram->config;
        $cleared = 0;

        foreach (array_keys($config->exercises) as $programExerciseId) {
            $overrides = $config->defaultExerciseOverrides((int) $programExerciseId);
            $cleared += $this->clearHistoricalBucket($overrides);
        }

        foreach ($config->allUserExerciseOverrides() as $userId => $overridesByExercise) {
            foreach (array_keys($overridesByExercise) as $programExerciseId) {
                $overrides = $config->userExerciseOverrides((int) $userId, (int) $programExerciseId);
                $cleared += $this->clearHistoricalBucket($overrides);
            }
        }

        if ($cleared > 0 && ! $dryRun) {
            $exerciseProgram->config = $config;
            $exerciseProgram->saveQuietly();
        }

        return $cleared;
    }

    private function clearHistoricalBucket(ExerciseOverrides $overrides): int
    {
        $count = count($overrides->historicalGridOverrides['sessions'] ?? [])
            + count($overrides->historicalGridOverrides['cells'] ?? []);

        if ($count > 0) {
            $overrides->historicalGridOverrides = ['sessions' => [], 'cells' => []];
        }

        return $count > 0 ? 1 : 0;
    }
}
