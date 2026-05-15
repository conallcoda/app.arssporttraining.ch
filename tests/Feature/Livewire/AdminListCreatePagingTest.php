<?php

use App\Livewire\Training\ExerciseProgramList;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramTypeEnum;
use App\Livewire\Database\AthleteList;
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

it('switches to a visible ownership tab when the current all tab filters hide the created program', function () {
    $coach = User::factory()->coach()->create();
    $otherCoach = User::factory()->coach()->create();

    ExerciseProgram::factory()->create([
        'owner_id' => $otherCoach->id,
        'name' => 'Other Coach Program',
    ]);

    $component = Livewire::actingAs($coach)
        ->test(ExerciseProgramList::class)
        ->set('selectedTab', 'all')
        ->set('filters', ['coach' => [$otherCoach->id]]);

    $component->call('handleFormSubmitted', [
        'name' => 'My New Program',
        'type' => ExerciseProgramTypeEnum::Program->value,
        'owner_id' => $coach->id,
        'internalTags' => [],
        'exercises' => [],
    ]);

    expect($component->get('selectedTab'))->toBe('mine')
        ->and($component->get('filters'))->not->toHaveKey('coach')
        ->and($component->instance()->items->currentPage())->toBe(1)
        ->and($component->instance()->items->pluck('name')->all())->toContain('My New Program')
        ->and($component->instance()->items->pluck('owner_id')->unique()->all())->toBe([$coach->id]);
});
