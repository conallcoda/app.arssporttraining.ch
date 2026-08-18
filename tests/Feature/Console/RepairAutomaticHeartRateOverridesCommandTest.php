<?php

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExercisePlanConfigOverride;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Users\User;
use App\Models\Users\UserGroup;

it('removes only program-level automatic heart-rate overrides derived from the preview fallback', function () {
    $group = UserGroup::create(['name' => 'Command Test Group']);
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['heartRate', 'heartRateZone'],
            'sets' => ['default' => 4],
            'heartRate' => ['mode' => 'automatic_biking', 'applyPer' => 'set'],
            'heartRateZone' => ['default' => '3', 'applyPer' => 'set'],
        ],
    ]);
    $program = ExerciseProgram::factory()->create();
    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);
    $trainingProgram = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);
    $athlete = User::factory()->athlete()->create();

    $fallbackHeartRate = createOverride($program, $pivot, null, 'heartRate', '125-144', 0);
    $fallbackZone = createOverride($program, $pivot, null, 'heartRateZone', '1', 0);
    $manualHeartRate = createOverride($program, $pivot, null, 'heartRate', '150-158', 1);
    $manualZone = createOverride($program, $pivot, null, 'heartRateZone', '1', 1);
    $athleteHeartRate = createOverride($program, $pivot, $athlete->id, 'heartRate', '125-144', 2);
    $athleteZone = createOverride($program, $pivot, $athlete->id, 'heartRateZone', '1', 2);
    $unpairedHeartRate = createOverride($program, $pivot, null, 'heartRate', '125-144', 3);

    $this->artisan('training:repair-automatic-heart-rate-overrides', [
        'trainingProgramId' => $trainingProgram->id,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(ExercisePlanConfigOverride::find($fallbackHeartRate->id))->not->toBeNull();

    $this->artisan('training:repair-automatic-heart-rate-overrides', [
        'trainingProgramId' => $trainingProgram->id,
        '--no-rebuild' => true,
    ])->assertSuccessful();

    expect(ExercisePlanConfigOverride::find($fallbackHeartRate->id))->toBeNull()
        ->and(ExercisePlanConfigOverride::find($fallbackZone->id))->not->toBeNull()
        ->and(ExercisePlanConfigOverride::find($manualHeartRate->id))->not->toBeNull()
        ->and(ExercisePlanConfigOverride::find($manualZone->id))->not->toBeNull()
        ->and(ExercisePlanConfigOverride::find($athleteHeartRate->id))->not->toBeNull()
        ->and(ExercisePlanConfigOverride::find($athleteZone->id))->not->toBeNull()
        ->and(ExercisePlanConfigOverride::find($unpairedHeartRate->id))->not->toBeNull();
});

function createOverride(
    ExerciseProgram $program,
    ExerciseProgramExercise $pivot,
    ?int $userId,
    string $setting,
    string $value,
    int $set,
): ExercisePlanConfigOverride {
    return ExercisePlanConfigOverride::create([
        'owner_type' => $program->getMorphClass(),
        'owner_id' => $program->id,
        'program_exercise_id' => $pivot->id,
        'user_id' => $userId,
        'scope' => 'current',
        'target' => 'cell',
        'week_index' => 0,
        'session_index' => 0,
        'set_index' => $set,
        'setting_key' => $setting,
        'value' => json_encode($value),
    ]);
}
