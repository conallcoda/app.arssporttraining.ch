<?php

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

it('runs the safe database import during seeding', function () {
    $command = m::mock(Command::class);

    $importSeeder = m::mock(DatabaseImportSeeder::class);
    $importSeeder->shouldReceive('setContainer')->once()->with(app())->andReturnSelf();
    $importSeeder->shouldReceive('setCommand')->once()->with($command)->andReturnSelf();
    $importSeeder->shouldReceive('run')->once();
    app()->instance(DatabaseImportSeeder::class, $importSeeder);

    $seeder = (new TestableDatabaseSeeder)
        ->setContainer(app())
        ->setCommand($command);

    $seeder->run();

    expect($seeder->calledSeeders)->toBe([
        CategoryShortNameSeeder::class,
    ]);
});
