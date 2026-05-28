<?php

use App\Livewire\Database\CategoryTree;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders exercise category roots and their children', function () {
    $conditioning = Tag::factory()->create([
        'scope' => 'exercise_category',
        'name' => 'Conditioning',
    ]);

    $strength = Tag::factory()->create([
        'scope' => 'exercise_category',
        'name' => 'Strength',
    ]);

    Tag::factory()->childOf($conditioning)->create([
        'name' => 'Intervals',
    ]);

    Tag::factory()->childOf($strength)->create([
        'name' => 'Squat',
    ]);

    Livewire::test(CategoryTree::class)
        ->assertSee('Conditioning')
        ->assertSee('Strength')
        ->assertSee('Intervals')
        ->assertDontSee('Squat');
});
