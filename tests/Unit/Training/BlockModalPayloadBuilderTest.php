<?php

use App\Models\Tag;
use App\Support\Training\BlockModalPayloadBuilder;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class);

it('builds category options from grouped programs', function () {
    $tag = Tag::factory()->make([
        'id' => 5,
        'name' => 'Strength',
        'slug' => 'strength',
    ]);

    $builder = new BlockModalPayloadBuilder;
    $options = $builder->categoryOptions(new Collection([
        0 => ['category' => null],
        5 => ['category' => $tag],
    ]));

    expect($options)->toBe([
        5 => ['name' => 'Strength', 'slug' => 'strength'],
    ]);
});

it('builds add and edit payloads for block modals', function () {
    $builder = new BlockModalPayloadBuilder;

    expect($builder->forAdd(10, 12, [3 => ['name' => 'Bike', 'slug' => 'bike']]))->toBe([
        'groupId' => 10,
        'userId' => 12,
        'categoryOptions' => [3 => ['name' => 'Bike', 'slug' => 'bike']],
    ]);

    expect($builder->forEdit(7, 10, 12, 5))->toBe([
        'blockId' => 7,
        'groupId' => 10,
        'userId' => 12,
        'parentId' => 5,
    ]);
});

it('builds dated block payloads with category metadata', function () {
    $tag = Tag::factory()->make([
        'name' => 'Conditioning',
        'slug' => 'conditioning',
    ]);

    $builder = new BlockModalPayloadBuilder;

    expect($builder->forDate('2026-03-10', 10, 12))->toBe([
        'date' => '2026-03-10',
        'groupId' => 10,
        'userId' => 12,
    ]);

    expect($builder->forDateRange('2026-03-10', '2026-03-17', 10, null))->toBe([
        'date' => '2026-03-10',
        'endDate' => '2026-03-17',
        'groupId' => 10,
        'userId' => null,
    ]);

    expect($builder->forCategoryDate('2026-03-10', 10, 12, 3, $tag))->toBe([
        'date' => '2026-03-10',
        'groupId' => 10,
        'userId' => 12,
        'categoryId' => 3,
        'categorySlug' => 'conditioning',
        'categoryName' => 'Conditioning',
    ]);

    expect($builder->forCategoryDateRange('2026-03-10', '2026-03-17', 10, null, 3, $tag))->toBe([
        'date' => '2026-03-10',
        'endDate' => '2026-03-17',
        'groupId' => 10,
        'userId' => null,
        'categoryId' => 3,
        'categorySlug' => 'conditioning',
        'categoryName' => 'Conditioning',
    ]);
});
