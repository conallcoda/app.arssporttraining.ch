<?php

use Coda\Cms\Models\Tag;
use Coda\Cms\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

it('preserves existing scope filtering while exposing extended pivot fields', function () {
    if (! Schema::hasColumn('taggables', 'score') || ! Schema::hasColumn('taggables', 'extra')) {
        Schema::table('taggables', function (Blueprint $table) {
            if (! Schema::hasColumn('taggables', 'score')) {
                $table->decimal('score', 5, 2)->nullable()->after('sort');
            }

            if (! Schema::hasColumn('taggables', 'extra')) {
                $table->json('extra')->nullable()->after('score');
            }
        });
    }

    $user = User::query()->create([
        'type' => 'coach',
        'forename' => 'Taylor',
        'surname' => 'Example',
        'email' => 'taylor@example.test',
    ]);

    $primaryTag = Tag::query()->create([
        'scope' => 'skills',
        'name' => 'Mobility',
    ]);

    $secondaryTag = Tag::query()->create([
        'scope' => 'goals',
        'name' => 'Strength',
    ]);

    $user->tags()->attach([
        $primaryTag->id => [
            'sort' => 1,
            'score' => 4.25,
            'extra' => ['source' => 'assessment'],
        ],
        $secondaryTag->id => [
            'sort' => 2,
        ],
    ]);

    $skillsTag = $user->tagsWithScope('skills')->firstOrFail();
    $goalsTag = $user->tagsWithScope('goals')->firstOrFail();

    expect($user->tags()->count())->toBe(2);
    expect($skillsTag->pivot->sort)->toBe(1);
    expect($skillsTag->pivot->score)->toBe('4.25');
    expect($skillsTag->pivot->extra)->toBe(['source' => 'assessment']);
    expect($goalsTag->pivot->score)->toBeNull();
    expect($goalsTag->pivot->extra)->toBeNull();
});
