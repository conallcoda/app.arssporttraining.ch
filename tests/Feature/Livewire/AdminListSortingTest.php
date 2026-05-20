<?php

use App\Livewire\Database\ExerciseList;
use App\Livewire\Training\ExerciseProgramList;
use App\Models\Exercise\ExerciseProgramTypeEnum;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Tag;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('sorts exercises by category name on the admin exercise list', function () {
    $coach = User::factory()->coach()->create();
    $alphaCategory = Tag::factory()->create(['scope' => 'exercise_category', 'name' => 'Alpha']);
    $zuluCategory = Tag::factory()->create(['scope' => 'exercise_category', 'name' => 'Zulu']);

    Exercise::create([
        'name' => 'Zulu Exercise',
        'category_id' => $zuluCategory->id,
        'owner_id' => $coach->id,
    ]);

    Exercise::create([
        'name' => 'Alpha Exercise',
        'category_id' => $alphaCategory->id,
        'owner_id' => $coach->id,
    ]);

    $component = Livewire::actingAs($coach)
        ->test(ExerciseList::class)
        ->set('sort', 'category');

    $names = $component->instance()->items->pluck('name')->values()->all();

    expect($names)->toBe(['Alpha Exercise', 'Zulu Exercise']);
});

it('sorts programs by coach name on the admin program list', function () {
    $signedInCoach = User::factory()->coach()->create(['forename' => 'Signed', 'surname' => 'In']);
    $adams = User::factory()->coach()->create(['forename' => 'Jane', 'surname' => 'Adams']);
    $smith = User::factory()->coach()->create(['forename' => 'John', 'surname' => 'Smith']);

    ExerciseProgram::create([
        'name' => 'Program by Smith',
        'type' => 'program',
        'owner_id' => $smith->id,
    ]);

    ExerciseProgram::create([
        'name' => 'Program by Adams',
        'type' => 'program',
        'owner_id' => $adams->id,
    ]);

    $component = Livewire::actingAs($signedInCoach)
        ->test(ExerciseProgramList::class)
        ->set('selectedTab', 'all')
        ->set('sort', 'coach');

    $names = $component->instance()->items->pluck('name')->values()->all();

    expect($names)->toBe(['Program by Adams', 'Program by Smith']);
});

it('sorts programs by type on the admin program list', function () {
    $coach = User::factory()->coach()->create();

    ExerciseProgram::create([
        'name' => 'Cool Down Program',
        'type' => 'warm_down',
        'owner_id' => $coach->id,
    ]);

    ExerciseProgram::create([
        'name' => 'Main Program',
        'type' => 'program',
        'owner_id' => $coach->id,
    ]);

    ExerciseProgram::create([
        'name' => 'Warm Up Program',
        'type' => 'warm_up',
        'owner_id' => $coach->id,
    ]);

    $component = Livewire::actingAs($coach)
        ->test(ExerciseProgramList::class)
        ->set('sort', 'type');

    $names = $component->instance()->items->pluck('name')->values()->all();

    expect($names)->toBe(['Main Program', 'Cool Down Program', 'Warm Up Program']);
});

it('shows the program category badge from the actual category instead of the shared color label', function () {
    $coach = User::factory()->coach()->create();

    $ski = Tag::factory()->create([
        'scope' => 'exercise_category',
        'name' => 'Ski',
        'color' => 'blue',
    ]);

    Tag::factory()->create([
        'scope' => 'exercise_category',
        'name' => 'Cool Down',
        'color' => 'blue',
    ]);

    $program = ExerciseProgram::create([
        'name' => 'Giant Slalom',
        'type' => ExerciseProgramTypeEnum::Program->value,
        'exercise_category_id' => $ski->id,
        'owner_id' => $coach->id,
    ]);

    $component = Livewire::actingAs($coach)
        ->test(ExerciseProgramList::class);

    $reflection = new ReflectionClass($component->instance());
    $method = $reflection->getMethod('getTable');
    $method->setAccessible(true);

    $table = $method->invoke($component->instance());
    $categoryColumn = collect($table->getColumns())->firstWhere('field', 'exerciseCategoryName');
    $data = \App\Data\Training\ExerciseProgramData::fromModel($program->fresh());

    expect($categoryColumn)->not->toBeNull();

    $badge = $categoryColumn->getSourceData($data);

    expect($badge)->toBe([[
        'label' => 'Ski',
        'color' => 'blue',
        'modalField' => 'exercise_category_id',
    ]]);
});

it('searches programs by category name as well as program title', function () {
    $coach = User::factory()->coach()->create();

    $ski = Tag::factory()->create([
        'scope' => 'exercise_category',
        'name' => 'Ski',
    ]);

    $strength = Tag::factory()->create([
        'scope' => 'exercise_category',
        'name' => 'Strength',
    ]);

    ExerciseProgram::create([
        'name' => 'Giant Slalom',
        'type' => ExerciseProgramTypeEnum::Program->value,
        'exercise_category_id' => $ski->id,
        'owner_id' => $coach->id,
    ]);

    ExerciseProgram::create([
        'name' => 'Back Squat Builder',
        'type' => ExerciseProgramTypeEnum::Program->value,
        'exercise_category_id' => $strength->id,
        'owner_id' => $coach->id,
    ]);

    $component = Livewire::actingAs($coach)
        ->test(ExerciseProgramList::class)
        ->set('filters.search', 'Ski');

    $names = $component->instance()->items->pluck('name')->values()->all();

    expect($names)->toBe(['Giant Slalom']);
});
