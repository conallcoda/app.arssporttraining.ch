<?php

use App\Livewire\Training\ExerciseProgramList;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Exercise\ExerciseProgramTypeEnum;
use App\Livewire\Database\AthleteList;
use App\Models\Tag;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('resolves the created athlete page within the active ownership tab', function () {
    $coach = User::factory()->coach()->create();
    $otherCoach = User::factory()->coach()->create();

    User::factory()->count(9)->athlete()->create([
        'owner_id' => $coach->id,
    ]);

    User::factory()->count(35)->athlete()->create([
        'owner_id' => $otherCoach->id,
    ]);

    $component = Livewire::actingAs($coach)
        ->test(AthleteList::class)
        ->set('selectedTab', 'mine');

    $component->call('handleFormSubmitted', [
        'forename' => 'New',
        'surname' => 'Athlete',
        'owner_id' => $coach->id,
        'internalTags' => [],
    ]);

    expect($component->instance()->items->currentPage())->toBe(1)
        ->and($component->instance()->items->count())->toBe(10)
        ->and($component->instance()->items->pluck('owner_id')->unique()->all())->toBe([$coach->id]);
});

it('duplicates a program from the admin program list using the existing program modal', function () {
    $coach = User::factory()->coach()->create();
    $category = Tag::factory()->withScope('exercise_category')->create();
    $programTag = Tag::factory()->withScope('program_internal')->create();

    $program = ExerciseProgram::factory()->create([
        'name' => '1A Strength',
        'type' => ExerciseProgramTypeEnum::Program,
        'exercise_category_id' => $category->id,
        'owner_id' => $coach->id,
    ]);
    $program->tags()->sync([$programTag->id]);

    $mainExercise = Exercise::factory()->create();
    $warmUpExercise = Exercise::factory()->create();

    $sourcePivots = collect([
        ExerciseProgramExercise::create([
            'exercise_program_id' => $program->id,
            'exercise_id' => $mainExercise->id,
            'sort' => 0,
            'type' => 'main',
        ]),
        ExerciseProgramExercise::create([
            'exercise_program_id' => $program->id,
            'exercise_id' => $warmUpExercise->id,
            'sort' => 0,
            'type' => 'warm_up',
        ]),
    ]);

    $component = Livewire::actingAs($coach)
        ->test(ExerciseProgramList::class);

    expect(collect($component->instance()->rowMenuActions)->pluck('name')->all())->toContain('duplicate');

    $component
        ->call('handleFormSubmitted', [
            '_duplicate_source_program_id' => $program->id,
            'name' => '1B Strength',
        ]);

    $duplicateProgram = ExerciseProgram::query()
        ->where('name', '1B Strength')
        ->firstOrFail();
    $duplicatePivots = ExerciseProgramExercise::query()
        ->where('exercise_program_id', $duplicateProgram->id)
        ->orderBy('type')
        ->orderBy('sort')
        ->get();

    expect($duplicateProgram->id)->not->toBe($program->id)
        ->and($duplicateProgram->type)->toBe($program->type)
        ->and($duplicateProgram->exercise_category_id)->toBe($category->id)
        ->and($duplicateProgram->owner_id)->toBe($coach->id)
        ->and($duplicateProgram->internalTags()->pluck('tags.id')->all())->toBe([$programTag->id])
        ->and($duplicatePivots)->toHaveCount(2)
        ->and($duplicatePivots->pluck('type')->sort()->values()->all())->toBe(['main', 'warm_up'])
        ->and($duplicatePivots->pluck('exercise_id')->sort()->values()->all())->toBe($sourcePivots->pluck('exercise_id')->sort()->values()->all())
        ->and($duplicatePivots->pluck('id')->intersect($sourcePivots->pluck('id'))->isEmpty())->toBeTrue();
});
