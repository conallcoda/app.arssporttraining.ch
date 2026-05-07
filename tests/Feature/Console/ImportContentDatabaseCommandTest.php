<?php

use App\Data\Coach\Settings\SessionGroupingSetting;
use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Models\Athlete\MetricSubmission;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseExternal;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('imports content without calendar data or manual program overrides', function () {
    $importPath = base_path('import/database/test-content-import');

    File::deleteDirectory($importPath);
    File::ensureDirectoryExists($importPath);

    $writeFixture = function (string $filename, array $data) use ($importPath): void {
        File::put($importPath.'/'.$filename, '<?php return '.var_export($data, true).';');
    };

    $writeFixture('tags.php', [
        [
            'id' => 10,
            'parent_id' => null,
            'scope' => 'exercise_category',
            'name' => 'Strength',
            'short_name' => null,
            'slug' => 'strength',
            'sort_order' => 0,
            'color' => null,
            'default_exercise_template_id' => null,
            'deleted_at' => null,
        ],
        [
            'id' => 302,
            'parent_id' => null,
            'scope' => 'exercise_category',
            'name' => 'Warm Up',
            'short_name' => 'WRM',
            'slug' => 'warm-up',
            'sort_order' => 1,
            'color' => 'slate',
            'default_exercise_template_id' => null,
            'deleted_at' => null,
        ],
    ]);

    $writeFixture('exercise_templates.php', [
        [
            'id' => 20,
            'owner_id' => null,
            'name' => 'Template A',
            'config' => ['settings' => []],
            'deleted_at' => null,
        ],
    ]);

    $writeFixture('exercises.php', [
        [
            'id' => 30,
            'owner_id' => null,
            'name' => 'Back Squat',
            'category_id' => 10,
            'external_id' => null,
            'template_id' => 20,
            'video_url' => null,
            'instructions' => null,
            'config' => ['settings' => ['reps']],
            'tags' => [],
            'deleted_at' => null,
        ],
        [
            'id' => 31,
            'owner_id' => null,
            'name' => 'Jogging',
            'category_id' => 302,
            'external_id' => null,
            'template_id' => 20,
            'video_url' => null,
            'instructions' => null,
            'config' => ['settings' => ['duration']],
            'tags' => [],
            'deleted_at' => null,
        ],
    ]);

    $writeFixture('exercise_externals.php', [
        [
            'id' => 32,
            'owner_id' => null,
            'source' => 'kilo',
            'name' => 'IndoorCycling',
            'video_url' => null,
            'category_id' => 302,
            'tags' => [],
            'deleted_at' => null,
        ],
    ]);

    $writeFixture('exercise_programs.php', [
        [
            'id' => 40,
            'owner_id' => null,
            'parent_type' => null,
            'parent_id' => null,
            'name' => 'Strength A',
            'type' => 'program',
            'exercise_category_id' => 10,
            'warm_up_program_id' => null,
            'warm_down_program_id' => null,
            'sort' => 0,
            'config' => [
                'target' => [
                    'measuredReps' => 1,
                    'measuredWeight' => 100,
                    'targetGoal' => 110,
                ],
                'exercises' => [
                    501 => [
                        'reps' => [
                            'mode' => 'manual',
                            'default' => 8,
                            'stepDownInterval' => 2,
                            'decrement' => 2,
                            'minimum' => 1,
                            'label' => '',
                            'applyPer' => 'set',
                        ],
                    ],
                ],
                'overrideValues' => [
                    [
                        'programExerciseId' => 501,
                        'userId' => null,
                        'scope' => 'current',
                        'target' => 'cell',
                        'week' => 0,
                        'session' => 0,
                        'set' => 0,
                        'settingKey' => 'reps',
                        'value' => 12,
                    ],
                ],
            ],
            'exercises' => [
                [
                    'id' => 501,
                    'exercise_id' => 30,
                    'sort' => 0,
                    'group' => null,
                    'type' => 'main',
                ],
            ],
            'deleted_at' => null,
        ],
    ]);

    $writeFixture('user_groups.php', [
        [
            'id' => 60,
            'owner_id' => null,
            'name' => 'Team Alpha',
            'config' => [],
            'deleted_at' => null,
        ],
    ]);

    $writeFixture('users.php', [
        [
            'id' => 69,
            'owner_id' => null,
            'type' => 'coach',
            'forename' => 'Casey',
            'surname' => 'Coach',
            'email' => 'casey@example.com',
            'phone' => null,
            'password' => '$2y$12$abcdefghijklmnopqrstuv',
            'gender' => null,
            'date_of_birth' => null,
            'color' => null,
            'config' => [],
            'groups' => [],
            'deleted_at' => null,
        ],
        [
            'id' => 70,
            'owner_id' => 69,
            'type' => 'athlete',
            'forename' => 'Alex',
            'surname' => 'Runner',
            'email' => 'alex@example.com',
            'phone' => null,
            'password' => '$2y$12$abcdefghijklmnopqrstuv',
            'gender' => null,
            'date_of_birth' => null,
            'color' => null,
            'config' => [],
            'groups' => [
                ['group_id' => 60, 'sort' => 0],
            ],
            'deleted_at' => null,
        ],
    ]);

    $writeFixture('training_programs.php', [
        [
            'id' => 80,
            'owner_id' => null,
            'group_id' => 60,
            'exercise_program_id' => 40,
            'sort' => 0,
        ],
    ]);

    $writeFixture('training_program_blocks.php', [
        [
            'id' => 81,
            'parent_id' => null,
            'owner_id' => null,
            'group_id' => 60,
            'user_id' => 70,
            'category_id' => 10,
            'type' => 'note',
            'start' => '2026-05-01',
            'end' => '2026-05-02',
            'note' => 'skip me',
            'color' => null,
            'config' => [],
            'active' => true,
            'deleted_at' => null,
        ],
    ]);

    $writeFixture('training_program_slots.php', [
        [
            'id' => 82,
            'training_program_id' => 80,
            'user_id' => 70,
            'owner_id' => null,
            'datetime' => '2026-05-01 09:00:00',
        ],
    ]);

    $writeFixture('metric_submissions.php', [
        [
            'id' => 83,
            'user_id' => 70,
            'metric' => 'readiness',
            'recorded_by' => 70,
            'recorded_at' => '2026-05-01 08:00:00',
            'owner_type' => null,
            'owner_id' => null,
            'deleted_at' => null,
            'values' => [
                [
                    'id' => 84,
                    'field' => 'score',
                    'value' => '5',
                ],
            ],
        ],
    ]);

    $this->artisan('db:import-content', ['path' => $importPath])->assertExitCode(0);

    expect(User::count())->toBe(2)
        ->and(ExerciseProgram::count())->toBe(1)
        ->and(TrainingProgram::count())->toBe(0)
        ->and(TrainingProgramBlock::count())->toBe(0)
        ->and(TrainingProgramSlot::count())->toBe(0)
        ->and(MetricSubmission::count())->toBe(0);

    $coach = User::query()->findOrFail(69);

    expect($coach->config->get('settings.'.SessionGroupingSetting::fieldsetKey()))
        ->toMatchArray([
            'mode' => SessionGroupingMode::Groups->value,
            'groupSize' => 2,
            'copyValuesAutomatically' => true,
        ]);

    $program = ExerciseProgram::query()->firstOrFail();
    $overrides = $program->config->defaultExerciseOverrides(501);

    expect($overrides->reps?->default)->toBe(8)
        ->and($overrides->gridOverrides['cells'] ?? [])->toBeEmpty()
        ->and($program->planConfigOverrides()->count())->toBe(0);

    $rawConfig = json_decode((string) $program->getRawOriginal('config'), true);

    expect($rawConfig['overrideValues'] ?? null)->toBeNull()
        ->and($rawConfig['exercises'][501]['gridOverrides'] ?? null)->toBeNull()
        ->and(Exercise::query()->where('name', 'Jogging')->exists())->toBeFalse()
        ->and(ExerciseExternal::query()->where('name', 'IndoorCycling')->exists())->toBeFalse()
        ->and(Tag::query()->where('id', 302)->exists())->toBeFalse();

    File::deleteDirectory($importPath);
});
