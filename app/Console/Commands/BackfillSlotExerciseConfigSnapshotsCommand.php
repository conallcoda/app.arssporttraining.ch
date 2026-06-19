<?php

namespace App\Console\Commands;

use App\Models\Training\ExerciseSettingSnapshot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Support\Training\EffectiveSlotExerciseConfigResolver;
use Illuminate\Console\Command;

class BackfillSlotExerciseConfigSnapshotsCommand extends Command
{
    protected $signature = 'training:backfill-slot-exercise-config-snapshots
        {--training-program-id= : Limit to one training program}
        {--user-id= : Limit to one athlete}
        {--slot-id=* : Limit to one or more slot ids}
        {--force : Apply the backfill instead of performing a dry run}';

    protected $description = 'Creates exercise setting snapshot rows and links scheduled slot exercises that do not have one.';

    public function handle(EffectiveSlotExerciseConfigResolver $resolver): int
    {
        $slotIds = collect((array) $this->option('slot-id'))
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        $force = (bool) $this->option('force');
        $scanned = 0;
        $updated = 0;
        $skipped = 0;

        TrainingProgramSlotExercise::query()
            ->with(['slot.trainingProgram.program.exercises', 'exercise', 'programExercise.exercise'])
            ->whereNull('exercise_setting_snapshot_id')
            ->when($this->filledOption('training-program-id'), function ($query): void {
                $query->whereHas('slot', function ($slotQuery): void {
                    $slotQuery->where('training_program_id', (int) $this->option('training-program-id'));
                });
            })
            ->when($this->filledOption('user-id'), function ($query): void {
                $query->whereHas('slot', function ($slotQuery): void {
                    $slotQuery->where('user_id', (int) $this->option('user-id'));
                });
            })
            ->when($slotIds !== [], fn ($query) => $query->whereIn('training_program_slot_id', $slotIds))
            ->orderBy('id')
            ->chunkById(200, function ($exercises) use ($resolver, $force, &$scanned, &$updated, &$skipped): void {
                foreach ($exercises as $exercise) {
                    $scanned++;
                    $config = $resolver->resolve($exercise);

                    if ($config === []) {
                        $skipped++;

                        continue;
                    }

                    if ($force) {
                        $snapshot = ExerciseSettingSnapshot::create([
                            'exercise_id' => $exercise->exercise_id,
                            'exercise_program_exercise_id' => $exercise->exercise_program_exercise_id,
                            'training_program_id' => $exercise->slot?->training_program_id,
                            'user_id' => $exercise->slot?->user_id,
                            'config' => $config,
                        ]);

                        $exercise->forceFill(['exercise_setting_snapshot_id' => $snapshot->id])->saveQuietly();
                    }

                    $updated++;
                }
            });

        if (! $force) {
            $this->warn('Dry run only. Re-run with --force to create and link snapshots.');
        }

        $this->line('Scanned slot exercises: '.$scanned);
        $this->line(($force ? 'Updated' : 'Would update').' slot exercises: '.$updated);
        $this->line('Skipped without resolvable config: '.$skipped);

        return self::SUCCESS;
    }

    private function filledOption(string $name): bool
    {
        $value = $this->option($name);

        return $value !== null && $value !== '';
    }
}
