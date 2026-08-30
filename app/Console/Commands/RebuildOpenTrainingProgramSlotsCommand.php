<?php

namespace App\Console\Commands;

use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Training\TrainingSessionEditGuard;
use App\Training\TrainingSessionRebuildService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RebuildOpenTrainingProgramSlotsCommand extends Command
{
    protected $signature = 'training:rebuild-open-slots
        {trainingProgramId : Calendar training program id}
        {--from= : Rebuild slots on or after this date}
        {--discard-athlete-overrides=* : Athlete ids whose current calendar grid overrides should be discarded before rebuilding}
        {--program-exercise=* : Limit discarded overrides to cloned program exercise pivot ids}';

    protected $description = 'Recompile mutable slots for one calendar training program without changing recorded sessions';

    public function handle(
        TrainingSessionEditGuard $editGuard,
        TrainingSessionRebuildService $rebuildService,
    ): int {
        $trainingProgramId = (int) $this->argument('trainingProgramId');
        $trainingProgram = TrainingProgram::with('program')->find($trainingProgramId);

        if (! $trainingProgram instanceof TrainingProgram) {
            $this->error("Training program {$trainingProgramId} was not found.");

            return self::FAILURE;
        }

        $from = $this->option('from');

        if ($from !== null && (! is_string($from) || strtotime($from) === false)) {
            $this->error("Invalid --from date: {$from}");

            return self::FAILURE;
        }

        $discardAthleteIds = collect($this->option('discard-athlete-overrides'))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $programExerciseIds = collect($this->option('program-exercise'))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($programExerciseIds->isNotEmpty() && $discardAthleteIds->isEmpty()) {
            $this->error('--program-exercise may only be used with --discard-athlete-overrides.');

            return self::FAILURE;
        }

        if ($programExerciseIds->isNotEmpty()) {
            $validProgramExerciseIds = ExerciseProgramExercise::query()
                ->where('exercise_program_id', $trainingProgram->exercise_program_id)
                ->whereIn('id', $programExerciseIds)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id);

            if ($validProgramExerciseIds->count() !== $programExerciseIds->count()) {
                $this->error('One or more --program-exercise ids do not belong to this training program; no overrides were discarded.');

                return self::FAILURE;
            }
        }

        foreach ($discardAthleteIds as $athleteId) {
            $hasSlots = TrainingProgramSlot::query()
                ->where('training_program_id', $trainingProgramId)
                ->where('user_id', $athleteId)
                ->exists();

            if (! $hasSlots) {
                $this->error("Athlete {$athleteId} has no slots in training program {$trainingProgramId}; no overrides were discarded.");

                return self::FAILURE;
            }
        }

        $baseQuery = TrainingProgramSlot::query()
            ->where('training_program_id', $trainingProgramId)
            ->whereNull('cancelled_at')
            ->when($from, fn (Builder $query) => $query->whereDate('datetime', '>=', $from));
        $immutable = $from
            ? (clone $baseQuery)
                ->where(fn (Builder $query) => $editGuard->applyImmutableSlotConstraints($query))
                ->count()
            : $editGuard->countImmutableSlotsForTrainingProgram($trainingProgramId);
        $mutable = (clone $baseQuery)
            ->whereNot(fn (Builder $query) => $editGuard->applyImmutableSlotConstraints($query))
            ->count();

        $discardedOverrideRows = 0;

        if ($discardAthleteIds->isNotEmpty()) {
            $program = $trainingProgram->program;
            $config = $program->config;

            foreach ($discardAthleteIds as $athleteId) {
                $discardedOverrideRows += $program->planConfigOverrides()
                    ->where('user_id', $athleteId)
                    ->where('scope', 'current')
                    ->when($programExerciseIds->isNotEmpty(), fn ($query) => $query->whereIn('program_exercise_id', $programExerciseIds))
                    ->count();

                foreach (array_keys($config->allUserExerciseOverrides()[$athleteId] ?? []) as $programExerciseId) {
                    if ($programExerciseIds->isNotEmpty() && ! $programExerciseIds->contains((int) $programExerciseId)) {
                        continue;
                    }

                    $overrides = $config->userExerciseOverrides($athleteId, (int) $programExerciseId);
                    $overrides->gridOverrides = ['sessions' => [], 'cells' => []];
                    $config->setUserExerciseOverrides($athleteId, (int) $programExerciseId, $overrides);
                }
            }

            if ($discardedOverrideRows > 0) {
                $program->config = $config;
                $program->save();
            }
        }

        $rebuildService->rebuildOpenSlotsForTrainingProgram(
            $trainingProgramId,
            is_string($from) && $from !== '' ? $from : null,
        );

        $this->info(sprintf(
            'Rebuilt %d mutable slots for training program %d (%s); preserved %d immutable slots.',
            $mutable,
            $trainingProgramId,
            $trainingProgram->program?->name ?? 'unknown program',
            $immutable,
        ));

        if ($discardAthleteIds->isNotEmpty()) {
            $this->info(sprintf(
                'Discarded %d current calendar override rows for athlete(s): %s.',
                $discardedOverrideRows,
                $discardAthleteIds->implode(', '),
            ));

            if ($programExerciseIds->isNotEmpty()) {
                $this->info('Discard scope was limited to program exercise pivot(s): '.$programExerciseIds->implode(', ').'.');
            }
        }

        return self::SUCCESS;
    }
}
