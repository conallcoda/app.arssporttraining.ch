<?php

use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use Database\Seeders\DatabaseImportSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery as m;

uses(RefreshDatabase::class);

it('links imported scheduled exercise programs back to their training program parent', function () {
    $importPath = base_path('import/database/test-scheduled-parent-import');

    File::deleteDirectory($importPath);
    File::ensureDirectoryExists($importPath);

    $writeFixture = function (string $filename, array $data) use ($importPath): void {
        File::put($importPath.'/'.$filename, '<?php return '.var_export($data, true).';');
    };

    $writeFixture('tags.php', [[
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
    ]]);

    $writeFixture('exercise_templates.php', []);
    $writeFixture('exercise_externals.php', []);
    $writeFixture('training_program_blocks.php', []);
    $writeFixture('metric_submissions.php', []);

    $writeFixture('exercises.php', [[
        'id' => 30,
        'owner_id' => null,
        'name' => 'Back Squat',
        'category_id' => 10,
        'external_id' => null,
        'template_id' => null,
        'video_url' => null,
        'instructions' => null,
        'config' => ['settings' => ['reps']],
        'tags' => [],
        'deleted_at' => null,
    ]]);

    $writeFixture('exercise_programs.php', [[
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
        'config' => [],
        'exercises' => [[
            'id' => 501,
            'exercise_id' => 30,
            'sort' => 0,
            'group' => null,
            'type' => 'main',
        ]],
        'deleted_at' => null,
    ]]);

    $writeFixture('user_groups.php', [[
        'id' => 60,
        'owner_id' => null,
        'name' => 'Team Alpha',
        'config' => [],
        'deleted_at' => null,
    ]]);

    $writeFixture('users.php', [[
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
    ]]);

    $writeFixture('training_programs.php', [[
        'id' => 80,
        'owner_id' => null,
        'group_id' => 60,
        'exercise_program_id' => 40,
        'sort' => 0,
    ]]);

    $writeFixture('training_program_slots.php', [[
        'id' => 82,
        'training_program_id' => 80,
        'user_id' => 69,
        'owner_id' => null,
        'datetime' => '2026-05-01 09:00:00',
    ]]);

    $command = m::mock(\Illuminate\Console\Command::class);
    $command->shouldReceive('info')->andReturnNull();

    app(DatabaseImportSeeder::class)
        ->setContainer(app())
        ->setCommand($command)
        ->usingImportPath($importPath)
        ->run();

    $trainingProgram = TrainingProgram::query()->findOrFail(80);
    $exerciseProgram = ExerciseProgram::query()->findOrFail(40);

    expect($exerciseProgram->parent_type)->toBe(TrainingProgram::class)
        ->and($exerciseProgram->parent_id)->toBe($trainingProgram->id);

    File::deleteDirectory($importPath);
});
