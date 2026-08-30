<?php

use App\Data\Exercise\Preview\SessionGroupingConfig;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingSessionMaterializer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reports only actual planned-value differences and never mutates slots', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Grouped audit']);
    $program = ExerciseProgram::factory()->create(['config' => ['weeks' => 2]]);
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 10, 'applyPer' => 'per_set'],
        ],
    ]);
    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);
    $config = $program->config;
    $overrides = $config->defaultExerciseOverrides($pivot->id);
    $overrides->sessionGrouping = SessionGroupingConfig::from([
        'mode' => 'groups',
        'groupSize' => 2,
        'copyValuesAutomatically' => false,
    ]);
    $overrides->gridOverrides = [
        'sessions' => [],
        'cells' => [
            ['week' => 0, 'session' => 1, 'set' => 0, 'data' => ['reps' => 8]],
        ],
    ];
    $config->setDefaultExerciseOverrides($pivot->id, $overrides);
    $program->config = $config;
    $program->save();

    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
        'created_at' => '2026-08-21 09:00:00',
    ]);
    $slots = collect(['2030-04-01 09:00:00', '2030-04-03 09:00:00'])
        ->map(fn (string $datetime) => TrainingProgramSlot::withoutEvents(fn () => TrainingProgramSlot::create([
            'training_program_id' => $trainingProgram->id,
            'user_id' => $athlete->id,
            'datetime' => $datetime,
            'scheduled_date' => substr($datetime, 0, 10),
        ])));

    $materializer = app(TrainingSessionMaterializer::class);
    $slots->each(fn (TrainingProgramSlot $slot) => $materializer->materialize($slot));
    $wrongSlot = $slots[1]->fresh('exercises.sets.values');
    $wrongSlot->exercises->first()->sets->first()->values->firstWhere('setting_key', 'reps')->update([
        'planned_value_type' => 'string',
        'planned_string_value' => '99',
    ]);

    $this->artisan('training:audit-grouped-session-override-values', [
        '--training-program' => [$trainingProgram->id],
    ])
        ->expectsOutputToContain('imports from 2026-08-20: 2 candidate slots, 1 programs with actual planned-value differences, 1 wrong slots, 1 wrong values')
        ->assertSuccessful();

    expect(groupedAuditRep($slots[0]))->toBe('10')
        ->and(groupedAuditRep($wrongSlot))->toBe('99');
});

function groupedAuditRep(TrainingProgramSlot $slot): ?string
{
    return $slot->fresh('exercises.sets.values')
        ->exercises->first()?->sets->first()?->values->firstWhere('setting_key', 'reps')?->planned_string_value;
}
