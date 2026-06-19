<?php

use App\Models\Training\ExerciseSettingSnapshot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Support\Training\EffectiveSlotExerciseConfigResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exercise_setting_snapshots')) {
            Schema::create('exercise_setting_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('exercise_id')->nullable()->index();
                $table->foreignId('exercise_program_exercise_id')->nullable()->index('ess_program_exercise_idx');
                $table->foreignId('training_program_id')->nullable()->index('ess_training_program_idx');
                $table->foreignId('user_id')->nullable()->index();
                $table->json('config');
                $table->timestamps();

                $table->index(
                    ['training_program_id', 'exercise_program_exercise_id', 'user_id'],
                    'ess_context_idx',
                );
            });
        }

        if (! Schema::hasColumn('training_program_slot_exercises', 'exercise_setting_snapshot_id')) {
            Schema::table('training_program_slot_exercises', function (Blueprint $table): void {
                $table->foreignId('exercise_setting_snapshot_id')
                    ->nullable()
                    ->after('type')
                    ->index('tpse_setting_snapshot_idx');
            });
        }

        $this->backfillMissingSnapshotsFromCurrentSettings();
    }

    public function down(): void
    {
        if (Schema::hasColumn('training_program_slot_exercises', 'exercise_setting_snapshot_id')) {
            Schema::table('training_program_slot_exercises', function (Blueprint $table): void {
                $table->dropIndex('tpse_setting_snapshot_idx');
                $table->dropColumn('exercise_setting_snapshot_id');
            });
        }

        Schema::dropIfExists('exercise_setting_snapshots');
    }

    private function backfillMissingSnapshotsFromCurrentSettings(): void
    {
        if (! Schema::hasColumn('training_program_slot_exercises', 'exercise_setting_snapshot_id')) {
            return;
        }

        $resolver = app(EffectiveSlotExerciseConfigResolver::class);

        TrainingProgramSlotExercise::query()
            ->with(['slot.trainingProgram.program.exercises', 'exercise', 'programExercise.exercise'])
            ->whereNull('exercise_setting_snapshot_id')
            ->orderBy('id')
            ->chunkById(200, function ($exercises) use ($resolver): void {
                foreach ($exercises as $exercise) {
                    $config = $resolver->resolve($exercise);

                    if ($config === []) {
                        continue;
                    }

                    $snapshot = ExerciseSettingSnapshot::create([
                        'exercise_id' => $exercise->exercise_id,
                        'exercise_program_exercise_id' => $exercise->exercise_program_exercise_id,
                        'training_program_id' => $exercise->slot?->training_program_id,
                        'user_id' => $exercise->slot?->user_id,
                        'config' => $config,
                    ]);

                    $exercise->forceFill(['exercise_setting_snapshot_id' => $snapshot->id])->saveQuietly();
                }
            });
    }
};
