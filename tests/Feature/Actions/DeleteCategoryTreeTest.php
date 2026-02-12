<?php

use App\Actions\DeleteCategoryTree;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseExternal;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('counts exercises assigned to a single category', function () {
    $category = Tag::factory()->create(['scope' => 'exercise_category']);
    Exercise::create(['name' => 'Bench Press', 'type' => 'strength_manual', 'category_id' => $category->id]);
    Exercise::create(['name' => 'Incline Press', 'type' => 'strength_manual', 'category_id' => $category->id]);

    $action = new DeleteCategoryTree;

    expect($action->getAffectedExerciseCount($category))->toBe(2);
});

it('counts exercises across descendants', function () {
    $parent = Tag::factory()->create(['scope' => 'exercise_category']);
    $child = Tag::factory()->childOf($parent)->create();
    $grandchild = Tag::factory()->childOf($child)->create();

    Exercise::create(['name' => 'Exercise A', 'type' => 'strength_manual', 'category_id' => $parent->id]);
    Exercise::create(['name' => 'Exercise B', 'type' => 'strength_manual', 'category_id' => $child->id]);
    Exercise::create(['name' => 'Exercise C', 'type' => 'strength_manual', 'category_id' => $grandchild->id]);

    $action = new DeleteCategoryTree;

    expect($action->getAffectedExerciseCount($parent))->toBe(3);
});

it('counts external exercises in affected count', function () {
    $category = Tag::factory()->create(['scope' => 'exercise_category']);
    Exercise::create(['name' => 'Exercise A', 'type' => 'strength_manual', 'category_id' => $category->id]);
    ExerciseExternal::create([
        'source' => 'kilo',
        'name' => 'External Exercise',
        'short_name' => 'ext',
        'template' => 'weight',
        'category_id' => $category->id,
    ]);

    $action = new DeleteCategoryTree;

    expect($action->getAffectedExerciseCount($category))->toBe(2);
});

it('returns zero when no exercises are assigned', function () {
    $category = Tag::factory()->create(['scope' => 'exercise_category']);

    $action = new DeleteCategoryTree;

    expect($action->getAffectedExerciseCount($category))->toBe(0);
});

it('counts descendants', function () {
    $parent = Tag::factory()->create(['scope' => 'exercise_category']);
    $child = Tag::factory()->childOf($parent)->create();
    Tag::factory()->childOf($child)->create();

    $action = new DeleteCategoryTree;

    expect($action->getDescendantCount($parent))->toBe(2);
});

it('returns zero descendants for a leaf node', function () {
    $leaf = Tag::factory()->create(['scope' => 'exercise_category']);

    $action = new DeleteCategoryTree;

    expect($action->getDescendantCount($leaf))->toBe(0);
});

it('deletes a leaf category', function () {
    $category = Tag::factory()->create(['scope' => 'exercise_category']);

    $action = new DeleteCategoryTree;
    $action->execute($category);

    expect(Tag::find($category->id))->toBeNull();
});

it('deletes a category and all its descendants', function () {
    $parent = Tag::factory()->create(['scope' => 'exercise_category']);
    $child = Tag::factory()->childOf($parent)->create();
    $grandchild = Tag::factory()->childOf($child)->create();

    $action = new DeleteCategoryTree;
    $action->execute($parent);

    expect(Tag::find($parent->id))->toBeNull();
    expect(Tag::find($child->id))->toBeNull();
    expect(Tag::find($grandchild->id))->toBeNull();
});

it('nullifies category_id on exercises when category is deleted', function () {
    $parent = Tag::factory()->create(['scope' => 'exercise_category']);
    $child = Tag::factory()->childOf($parent)->create();

    $exercise1 = Exercise::create(['name' => 'Exercise A', 'type' => 'strength_manual', 'category_id' => $parent->id]);
    $exercise2 = Exercise::create(['name' => 'Exercise B', 'type' => 'strength_manual', 'category_id' => $child->id]);

    $action = new DeleteCategoryTree;
    $action->execute($parent);

    expect($exercise1->fresh()->category_id)->toBeNull();
    expect($exercise2->fresh()->category_id)->toBeNull();
});

it('does not delete unrelated categories', function () {
    $target = Tag::factory()->create(['scope' => 'exercise_category', 'name' => 'Target']);
    $unrelated = Tag::factory()->create(['scope' => 'exercise_category', 'name' => 'Unrelated']);

    $action = new DeleteCategoryTree;
    $action->execute($target);

    expect(Tag::find($unrelated->id))->not->toBeNull();
});
