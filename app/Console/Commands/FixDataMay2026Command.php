<?php

namespace App\Console\Commands;

use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixDataMay2026Command extends Command
{
    public const SIGNATURE = 'data:fix-may-2026';

    protected $signature = self::SIGNATURE;

    protected $description = 'Repairs scheduled-program ownership data introduced by legacy imports and stale program links';

    public function handle(): int
    {
        $summary = DB::transaction(function (): array {
            return [
                'shared_programs_split' => $this->fixSharedScheduledExercisePrograms(),
                'library_programs_cloned' => $this->fixTrainingProgramsUsingLibraryPrograms(),
                'scheduled_program_parents_relinked' => $this->fixMismatchedScheduledProgramParents(),
                'orphaned_programs_deleted' => $this->fixOrphanedScheduledExercisePrograms(),
            ];
        });

        $this->info('FixDataMay2026 complete.');
        $this->line("Shared scheduled programs split: {$summary['shared_programs_split']}");
        $this->line("Library programs cloned for scheduled ownership: {$summary['library_programs_cloned']}");
        $this->line("Scheduled program parents relinked: {$summary['scheduled_program_parents_relinked']}");
        $this->line("Orphaned scheduled programs deleted: {$summary['orphaned_programs_deleted']}");

        return self::SUCCESS;
    }

    private function fixSharedScheduledExercisePrograms(): int
    {
        $createdClones = 0;

        $sharedExerciseProgramIds = TrainingProgram::query()
            ->selectRaw('exercise_program_id, COUNT(*) as reference_count')
            ->groupBy('exercise_program_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('exercise_program_id');

        foreach ($sharedExerciseProgramIds as $exerciseProgramId) {
            $exerciseProgram = ExerciseProgram::query()->find($exerciseProgramId);

            if (! $exerciseProgram) {
                continue;
            }

            $trainingPrograms = TrainingProgram::query()
                ->where('exercise_program_id', $exerciseProgramId)
                ->orderBy('id')
                ->get();

            if ($trainingPrograms->count() <= 1) {
                continue;
            }

            $scheduledOwnerId = $exerciseProgram->parent_type === TrainingProgram::class
                ? (int) ($exerciseProgram->parent_id ?? 0)
                : 0;

            $keepExistingClone = $scheduledOwnerId > 0
                && $trainingPrograms->contains(fn (TrainingProgram $trainingProgram): bool => $trainingProgram->id === $scheduledOwnerId);

            if ($keepExistingClone) {
                foreach ($trainingPrograms as $trainingProgram) {
                    if ($trainingProgram->id === $scheduledOwnerId) {
                        continue;
                    }

                    $this->assignClonedExerciseProgramToTrainingProgram($exerciseProgram, $trainingProgram);
                    $createdClones++;
                }

                continue;
            }

            foreach ($trainingPrograms as $trainingProgram) {
                $this->assignClonedExerciseProgramToTrainingProgram($exerciseProgram, $trainingProgram);
                $createdClones++;
            }
        }

        return $createdClones;
    }

    private function fixTrainingProgramsUsingLibraryPrograms(): int
    {
        $createdClones = 0;

        TrainingProgram::query()
            ->with('program')
            ->orderBy('id')
            ->chunkById(100, function ($trainingPrograms) use (&$createdClones): void {
                foreach ($trainingPrograms as $trainingProgram) {
                    $exerciseProgram = $trainingProgram->program;

                    if (! $exerciseProgram || $exerciseProgram->parent_type === TrainingProgram::class) {
                        continue;
                    }

                    $references = TrainingProgram::query()
                        ->where('exercise_program_id', $exerciseProgram->id)
                        ->count();

                    if ($references !== 1) {
                        continue;
                    }

                    $this->assignClonedExerciseProgramToTrainingProgram($exerciseProgram, $trainingProgram);
                    $createdClones++;
                }
            });

        return $createdClones;
    }

    private function fixMismatchedScheduledProgramParents(): int
    {
        $relinked = 0;

        TrainingProgram::query()
            ->with('program')
            ->orderBy('id')
            ->chunkById(100, function ($trainingPrograms) use (&$relinked): void {
                foreach ($trainingPrograms as $trainingProgram) {
                    $exerciseProgram = $trainingProgram->program;

                    if (! $exerciseProgram || $exerciseProgram->parent_type !== TrainingProgram::class) {
                        continue;
                    }

                    if ((int) $exerciseProgram->parent_id === $trainingProgram->id) {
                        continue;
                    }

                    $exerciseProgram->update([
                        'parent_type' => TrainingProgram::class,
                        'parent_id' => $trainingProgram->id,
                    ]);

                    $relinked++;
                }
            });

        return $relinked;
    }

    private function fixOrphanedScheduledExercisePrograms(): int
    {
        $deleted = 0;

        ExerciseProgram::query()
            ->where('parent_type', TrainingProgram::class)
            ->whereNotNull('parent_id')
            ->orderBy('id')
            ->chunkById(100, function ($exercisePrograms) use (&$deleted): void {
                foreach ($exercisePrograms as $exerciseProgram) {
                    $hasParent = TrainingProgram::query()
                        ->whereKey($exerciseProgram->parent_id)
                        ->exists();

                    $hasReferences = TrainingProgram::query()
                        ->where('exercise_program_id', $exerciseProgram->id)
                        ->exists();

                    if ($hasParent || $hasReferences) {
                        continue;
                    }

                    $exerciseProgram->delete();
                    $deleted++;
                }
            });

        return $deleted;
    }

    private function assignClonedExerciseProgramToTrainingProgram(ExerciseProgram $sourceProgram, TrainingProgram $trainingProgram): void
    {
        $clone = $sourceProgram->duplicate();

        $clone->update([
            'parent_type' => TrainingProgram::class,
            'parent_id' => $trainingProgram->id,
        ]);

        $trainingProgram->update([
            'exercise_program_id' => $clone->id,
        ]);
    }
}
