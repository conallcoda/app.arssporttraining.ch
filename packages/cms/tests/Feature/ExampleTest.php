<?php

use Coda\Cms\Tests\Fixtures\CmsTestItem;
use Coda\Cms\Tests\Fixtures\CmsTestItemData;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('cms_test_items', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('status')->nullable();
        $table->integer('priority')->default(0);
        $table->foreignId('parent_id')->nullable();
        $table->integer('sort_order')->default(0);
        $table->boolean('is_active')->default(true);
        $table->date('published_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

it('can run a CMS package feature test', function () {
    expect(config('cms.home'))->toBeString();
});

it('creates a test item via factory', function () {
    $item = CmsTestItem::factory()->create(['name' => 'Test Widget']);

    expect($item->name)->toBe('Test Widget');
    expect($item->status)->toBe('draft');
    expect($item->is_active)->toBeTrue();
});

it('creates a test item via DTO persist', function () {
    $data = new CmsTestItemData(name: 'From DTO', status: 'published', priority: 5);
    $data->persist();

    expect($data->id)->not->toBeNull();
    expect(CmsTestItem::find($data->id)->name)->toBe('From DTO');
});

it('builds form from test DTO', function () {
    $form = CmsTestItemData::getForm();

    $fieldsets = $form->resolveFieldsets(['status' => 'draft']);
    expect($fieldsets)->toHaveCount(1);
    expect($fieldsets[0]->fields)->toHaveCount(4);

    $fieldsets = $form->resolveFieldsets(['status' => 'published']);
    expect($fieldsets[0]->fields)->toHaveCount(5);
});

it('creates parent-child relationships', function () {
    $parent = CmsTestItem::factory()->create(['name' => 'Parent']);
    $child = CmsTestItem::factory()->childOf($parent)->create(['name' => 'Child']);

    expect($child->parent_id)->toBe($parent->id);
    expect($parent->children)->toHaveCount(1);
    expect($parent->children->first()->name)->toBe('Child');
});
