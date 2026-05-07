<?php

use App\Support\Import\ExerciseFixtureImporter;
use Database\Seeders\CategoryShortNameSeeder;
use Database\Seeders\DatabaseImportSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Mockery as m;

class TestableDatabaseSeeder extends DatabaseSeeder
{
    public array $calledSeeders = [];

    public function call($class, $silent = false, array $parameters = [])
    {
        $this->calledSeeders = is_array($class) ? $class : [$class];

        return $this;
    }
}

afterEach(function () {
    putenv('DB_SEED_PROFILE');
    unset($_ENV['DB_SEED_PROFILE'], $_SERVER['DB_SEED_PROFILE']);
});

it('runs content import and then the Conall fixture for the default seed profile', function () {
    putenv('DB_SEED_PROFILE=content-import');
    $_ENV['DB_SEED_PROFILE'] = 'content-import';
    $_SERVER['DB_SEED_PROFILE'] = 'content-import';

    $command = m::mock(Command::class);

    $importSeeder = m::mock(DatabaseImportSeeder::class);
    $importSeeder->shouldReceive('setContainer')->once()->with(app())->andReturnSelf();
    $importSeeder->shouldReceive('setCommand')->once()->with($command)->andReturnSelf();
    $importSeeder->shouldReceive('contentOnly')->once()->andReturnSelf();
    $importSeeder->shouldReceive('run')->once();
    app()->instance(DatabaseImportSeeder::class, $importSeeder);

    $fixtureImporter = m::mock(ExerciseFixtureImporter::class);
    $fixtureImporter->shouldReceive('import')
        ->once()
        ->with(base_path('import/fixture'), $command);
    app()->instance(ExerciseFixtureImporter::class, $fixtureImporter);

    $seeder = (new TestableDatabaseSeeder)
        ->setContainer(app())
        ->setCommand($command);

    $seeder->run();

    expect($seeder->calledSeeders)->toBe([
        CategoryShortNameSeeder::class,
    ]);
});

it('runs only the Conall fixture for the exercise-fixture seed profile', function () {
    putenv('DB_SEED_PROFILE=exercise-fixture');
    $_ENV['DB_SEED_PROFILE'] = 'exercise-fixture';
    $_SERVER['DB_SEED_PROFILE'] = 'exercise-fixture';

    $command = m::mock(Command::class);

    $importSeeder = m::mock(DatabaseImportSeeder::class);
    $importSeeder->shouldNotReceive('run');
    app()->instance(DatabaseImportSeeder::class, $importSeeder);

    $fixtureImporter = m::mock(ExerciseFixtureImporter::class);
    $fixtureImporter->shouldReceive('import')
        ->once()
        ->with(base_path('import/fixture'), $command);
    app()->instance(ExerciseFixtureImporter::class, $fixtureImporter);

    $seeder = (new TestableDatabaseSeeder)
        ->setContainer(app())
        ->setCommand($command);

    $seeder->run();

    expect($seeder->calledSeeders)->toBe([
        CategoryShortNameSeeder::class,
    ]);
});
