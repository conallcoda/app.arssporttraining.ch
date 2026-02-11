<?php

use App\Models\Exercise\Exercise;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a slug from the name', function () {
    $tag = Tag::factory()->create(['name' => 'Upper Back', 'scope' => 'muscle_group']);

    expect($tag->slug)->toBe('upper-back');
});

it('allows the same slug in different scopes', function () {
    $tag1 = Tag::factory()->create(['name' => 'Chest', 'scope' => 'muscle_group']);
    $tag2 = Tag::factory()->create(['name' => 'Chest', 'scope' => 'equipment']);

    expect($tag1->slug)->toBe('chest');
    expect($tag2->slug)->toBe('chest');
});

it('generates unique slugs within the same scope', function () {
    $tag1 = Tag::factory()->create(['name' => 'Chest', 'scope' => 'muscle_group']);
    $tag2 = Tag::factory()->create(['name' => 'Chest', 'scope' => 'muscle_group']);

    expect($tag1->slug)->toBe('chest');
    expect($tag2->slug)->not->toBe('chest');
});

it('filters tags by scope', function () {
    Tag::factory()->create(['scope' => 'muscle_group']);
    Tag::factory()->create(['scope' => 'equipment']);

    expect(Tag::forScope('muscle_group')->count())->toBe(1);
    expect(Tag::forScope('equipment')->count())->toBe(1);
});

it('soft deletes a tag', function () {
    $tag = Tag::factory()->create();
    $tag->delete();

    expect($tag->trashed())->toBeTrue();
    expect(Tag::withTrashed()->find($tag->id))->not->toBeNull();
    expect(Tag::find($tag->id))->toBeNull();
});

it('attaches tags to a taggable model', function () {
    $exercise = Exercise::create(['name' => 'Bench Press', 'type' => 'strength_manual']);
    $tag = Tag::factory()->create(['scope' => 'muscle_group', 'name' => 'Chest']);

    $exercise->tags()->attach($tag);

    expect($exercise->tags)->toHaveCount(1);
    expect($exercise->tags->first()->name)->toBe('Chest');
});

it('filters tags by scope on a taggable model', function () {
    $exercise = Exercise::create(['name' => 'Bench Press', 'type' => 'strength_manual']);
    $muscleTag = Tag::factory()->create(['scope' => 'muscle_group', 'name' => 'Chest']);
    $equipTag = Tag::factory()->create(['scope' => 'equipment', 'name' => 'Barbell']);

    $exercise->tags()->attach([$muscleTag->id, $equipTag->id]);

    expect($exercise->tagsWithScope('muscle_group')->get())->toHaveCount(1);
    expect($exercise->tagsWithScope('equipment')->get())->toHaveCount(1);
});

it('detaches tags without deleting the tag', function () {
    $exercise = Exercise::create(['name' => 'Bench Press', 'type' => 'strength_manual']);
    $tag = Tag::factory()->create();

    $exercise->tags()->attach($tag);
    $exercise->tags()->detach($tag);

    expect($exercise->tags()->count())->toBe(0);
    expect(Tag::find($tag->id))->not->toBeNull();
});

it('preserves sort order on the pivot', function () {
    $exercise = Exercise::create(['name' => 'Bench Press', 'type' => 'strength_manual']);
    $tag1 = Tag::factory()->create(['scope' => 'muscle_group', 'name' => 'Chest']);
    $tag2 = Tag::factory()->create(['scope' => 'muscle_group', 'name' => 'Triceps']);
    $tag3 = Tag::factory()->create(['scope' => 'muscle_group', 'name' => 'Shoulders']);

    $exercise->tags()->attach([
        $tag3->id => ['sort' => 0],
        $tag1->id => ['sort' => 1],
        $tag2->id => ['sort' => 2],
    ]);

    $tags = $exercise->tags()->get();

    expect($tags[0]->id)->toBe($tag3->id);
    expect($tags[1]->id)->toBe($tag1->id);
    expect($tags[2]->id)->toBe($tag2->id);
    expect($tags[0]->pivot->sort)->toBe(0);
    expect($tags[1]->pivot->sort)->toBe(1);
    expect($tags[2]->pivot->sort)->toBe(2);
});
