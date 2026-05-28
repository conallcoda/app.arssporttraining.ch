<?php

use App\Livewire\Training\CategoryList;
use App\Data\Training\CategoryData;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders categories as a collapsed tree by default', function () {
    $conditioning = Tag::factory()->create([
        'scope' => 'exercise_category',
        'name' => 'Conditioning',
    ]);

    Tag::factory()->childOf($conditioning)->create([
        'name' => 'Intervals',
        'scope' => 'exercise_category',
    ]);

    Livewire::test(CategoryList::class)
        ->assertSee('Conditioning')
        ->assertSee('Intervals')
        ->assertSee('defaultExpandedKeys: []', false);
});

it('creates child categories from the tree form submission', function () {
    $root = Tag::factory()->create([
        'scope' => 'exercise_category',
        'name' => 'Conditioning',
    ]);

    Livewire::test(CategoryList::class)
        ->call('handleFormSubmitted', [
            'id' => null,
            'name' => 'Intervals',
            'shortName' => 'int',
            'color' => 'blue',
            'parentId' => $root->id,
            'defaultExerciseTemplate' => null,
        ]);

    $child = Tag::query()
        ->forScope('exercise_category')
        ->where('name', 'Intervals')
        ->first();

    expect($child)->not->toBeNull()
        ->and($child->parent_id)->toBe($root->id)
        ->and($child->short_name)->toBeNull()
        ->and($child->sort_order)->toBe(0);
});

it('shows color and short name fields only for top level category forms', function () {
    $form = CategoryData::getForm();

    $rootFieldsets = $form->resolveFieldsets([
        'parentId' => null,
    ]);

    $childFieldsets = $form->resolveFieldsets([
        'parentId' => 123,
    ]);

    expect(collect($rootFieldsets[0]->fields)->pluck('name')->all())
        ->toContain('color')
        ->toContain('shortName')
        ->and(collect($childFieldsets[0]->fields)->pluck('name')->all())
        ->not->toContain('color')
        ->not->toContain('shortName');
});
