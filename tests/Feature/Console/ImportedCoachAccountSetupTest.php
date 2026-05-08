<?php

use App\Models\Users\AccountSetupStatus;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('marks the fixture owner coach as active after importing the exercise fixture', function () {
    $envPath = base_path('tests/Fixtures/tmp.exercise-fixture.env');
    File::ensureDirectoryExists(dirname($envPath));
    File::put($envPath, "APP_ENV=testing\n");
    config()->set('app.test_user_env_file', $envPath);

    $this->artisan('db:import-exercise-fixture')->assertExitCode(0);

    $coach = User::query()->where('email', 'conall@coda.works')->firstOrFail();

    expect($coach->accountSetupStatus())->toBe(AccountSetupStatus::Active)
        ->and($coach->account_setup_completed_at)->not->toBeNull();
});

it('marks imported content coaches as active and preserves their email', function () {
    $importPath = base_path('tests/Fixtures/tmp.content-import-coach-active');

    File::deleteDirectory($importPath);
    File::ensureDirectoryExists($importPath);

    $writeFixture = function (string $filename, array $data) use ($importPath): void {
        File::put($importPath.'/'.$filename, '<?php return '.var_export($data, true).';');
    };

    $writeFixture('tags.php', []);
    $writeFixture('exercise_templates.php', []);
    $writeFixture('exercises.php', []);
    $writeFixture('exercise_externals.php', []);
    $writeFixture('exercise_programs.php', []);
    $writeFixture('user_groups.php', []);
    $writeFixture('training_programs.php', []);
    $writeFixture('training_program_blocks.php', []);
    $writeFixture('training_program_slots.php', []);
    $writeFixture('metric_submissions.php', []);

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
    ]);

    $this->artisan('db:import-content', ['path' => $importPath])->assertExitCode(0);

    $coach = User::query()->findOrFail(69);

    expect($coach->email)->toBe('casey@example.com')
        ->and($coach->accountSetupStatus())->toBe(AccountSetupStatus::Active)
        ->and($coach->account_setup_completed_at)->not->toBeNull();

    File::deleteDirectory($importPath);
});
