<?php

use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Training\Config\ExerciseOverrides;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingSessionRebuildService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rebuilds future slots from changed defaults without rewriting frozen past slots', function () {
    Carbon::setTestNow('2026-04-17 12:00:00');

    [$athlete, $program, $pivot, $trainingProgram] = buildOracleProgramContext();

    $pastSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-10 09:00:00'),
    ]);
    $futureSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-20 09:00:00'),
    ]);

    expect(slotReps($pastSlot))->toBe('5')
        ->and(slotReps($futureSlot))->toBe('5');

    $config = $program->config;
    $config->setDefaultExerciseOverrides($pivot->id, ExerciseOverrides::from([
        'reps' => RepsSetting::from(['mode' => 'manual', 'default' => 8, 'applyPer' => 'session']),
    ]));
    $program->config = $config;
    $program->save();

    app(TrainingSessionRebuildService::class)->rebuildFutureSlotsForTrainingProgramAthlete($trainingProgram->id, $athlete->id);

    expect(slotReps($pastSlot))->toBe('5')
        ->and(slotReps($futureSlot))->toBe('8');
});

it('applies athlete-specific overrides ahead of plan defaults when materializing scheduled slots', function () {
    Carbon::setTestNow('2026-04-17 12:00:00');

    [$athleteOne, $program, $pivot, $trainingProgram] = buildOracleProgramContext();
    $athleteTwo = User::factory()->athlete()->create();

    $config = $program->config;
    $config->setDefaultExerciseOverrides($pivot->id, ExerciseOverrides::from([
        'reps' => RepsSetting::from(['mode' => 'manual', 'default' => 8, 'applyPer' => 'session']),
    ]));
    $config->setUserExerciseOverrides($athleteOne->id, $pivot->id, ExerciseOverrides::from([
        'reps' => RepsSetting::from(['mode' => 'manual', 'default' => 10, 'applyPer' => 'session']),
    ]));
    $program->config = $config;
    $program->save();

    $athleteOneSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athleteOne->id,
        'datetime' => Carbon::parse('2026-04-20 09:00:00'),
    ]);
    $athleteTwoSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athleteTwo->id,
        'datetime' => Carbon::parse('2026-04-20 09:00:00'),
    ]);

    expect(slotReps($athleteOneSlot))->toBe('10')
        ->and(slotReps($athleteTwoSlot))->toBe('8');
});

it('falls back to the plan default when an athlete override is removed while preserving frozen past slots', function () {
    Carbon::setTestNow('2026-04-17 12:00:00');

    [$athlete, $program, $pivot, $trainingProgram] = buildOracleProgramContext();

    $config = $program->config;
    $config->setDefaultExerciseOverrides($pivot->id, ExerciseOverrides::from([
        'reps' => RepsSetting::from(['mode' => 'manual', 'default' => 8, 'applyPer' => 'session']),
    ]));
    $config->setUserExerciseOverrides($athlete->id, $pivot->id, ExerciseOverrides::from([
        'reps' => RepsSetting::from(['mode' => 'manual', 'default' => 10, 'applyPer' => 'session']),
    ]));
    $program->config = $config;
    $program->save();

    $pastSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-10 09:00:00'),
    ]);
    $futureSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-20 09:00:00'),
    ]);

    expect(slotReps($pastSlot))->toBe('10')
        ->and(slotReps($futureSlot))->toBe('10');

    $config = $program->fresh()->config;
    $config->removeUserExerciseOverrides($athlete->id, $pivot->id);
    $program->config = $config;
    $program->save();

    app(TrainingSessionRebuildService::class)->rebuildFutureSlotsForTrainingProgramAthlete($trainingProgram->id, $athlete->id);

    expect(slotReps($pastSlot))->toBe('10')
        ->and(slotReps($futureSlot))->toBe('8');
});

/**
 * @return array{0: User, 1: ExerciseProgram, 2: ExerciseProgramExercise, 3: TrainingProgram}
 */
function buildOracleProgramContext(): array
{
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Oracle Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Oracle Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Front Squat',
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'session'],
        ],
    ]);

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    return [$athlete, $program, $pivot, $trainingProgram];
}

function slotReps(TrainingProgramSlot $slot): ?string
{
    return $slot->fresh('exercises.sets.values')
        ->exercises
        ->first()?->sets
        ->first()?->values
        ->firstWhere('setting_key', 'reps')?->planned_string_value;
}
