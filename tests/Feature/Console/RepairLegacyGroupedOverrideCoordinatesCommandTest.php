<?php

use App\Data\Exercise\Preview\SessionGroupingConfig;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingRevisionBatch;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('dry-runs then audibly remaps selected legacy grouped coordinates', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Legacy grouped repair']);
    $program = ExerciseProgram::factory()->create(['config' => ['weeks' => 3]]);
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
    $overrides = $config->userExerciseOverrides($athlete->id, $pivot->id);
    $overrides->sessionGrouping = SessionGroupingConfig::from([
        'mode' => 'groups',
        'groupSize' => 2,
        'copyValuesAutomatically' => false,
    ]);
    $overrides->gridOverrides = [
        'sessions' => [],
        'cells' => [
            ['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => '8']],
            ['week' => 1, 'session' => 0, 'set' => 0, 'data' => ['reps' => '9']],
            ['week' => 2, 'session' => 0, 'set' => 0, 'data' => ['reps' => '10']],
        ],
    ];
    $config->setUserExerciseOverrides($athlete->id, $pivot->id, $overrides);
    $program->config = $config;
    $program->save();
    $program->planConfigOverrides()->update([
        'created_at' => '2026-08-25 10:00:00',
        'updated_at' => '2026-08-25 10:00:00',
    ]);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);
    TrainingProgramSlot::withoutEvents(fn () => TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-01 09:00:00',
    ]));
    $report = storage_path('framework/testing/legacy-grouped-coordinate-repair.json');

    try {
        $arguments = [
            'trainingProgramId' => $trainingProgram->id,
            '--user' => [$athlete->id],
            '--program-exercise' => [$pivot->id],
            '--report' => $report,
        ];

        $this->artisan('training:repair-legacy-grouped-overrides', $arguments)
            ->expectsOutputToContain('Planned 2 coordinate changes')
            ->expectsOutputToContain('Dry run only')
            ->assertSuccessful();

        expect($program->planConfigOverrides()->orderBy('week_index')->get()
            ->map(fn ($row): array => [$row->week_index, $row->session_index])
            ->all())->toBe([[0, 0], [1, 0], [2, 0]]);

        $this->artisan('training:repair-legacy-grouped-overrides', [
            ...$arguments,
            '--apply' => true,
            '--updated-by' => $coach->id,
        ])
            ->expectsOutputToContain('Applied 2 coordinate changes without rebuilding slots')
            ->assertSuccessful();

        $coordinates = $program->planConfigOverrides()
            ->orderBy('week_index')
            ->orderBy('session_index')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                $row->week_index.':'.$row->session_index => $row->getDecodedValue(),
            ])
            ->all();

        expect($coordinates)->toBe(['0:0' => '8', '0:1' => '9', '1:0' => '10'])
            ->and(TrainingRevisionBatch::query()
                ->where('owner_type', $program::class)
                ->where('owner_id', $program->id)
                ->where('action', 'repair_legacy_grouped_override_coordinates')
                ->first()?->stateRevisions()->count())->toBe(2);
    } finally {
        File::delete($report);
    }
});
