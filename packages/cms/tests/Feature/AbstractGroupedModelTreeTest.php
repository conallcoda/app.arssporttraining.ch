<?php

use Coda\Cms\Tests\Fixtures\CmsTestItem;
use Coda\Cms\Tests\Fixtures\CmsTestLeaf;
use Coda\Cms\Tests\Fixtures\TestGroupedTree;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

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

    Schema::create('cms_test_leaves', function ($table) {
        $table->id();
        $table->foreignId('group_id')->nullable();
        $table->string('name');
        $table->timestamps();
    });

    Livewire::component('test-grouped-tree', TestGroupedTree::class);
});

it('flattens mixed group and leaf nodes', function () {
    $root = CmsTestItem::factory()->create(['name' => 'Root', 'sort_order' => 0]);
    $child = CmsTestItem::factory()->childOf($root)->create(['name' => 'Child', 'sort_order' => 0]);
    $leaf = CmsTestLeaf::query()->create(['group_id' => $child->id, 'name' => 'Leaf A']);

    $instance = Livewire::test(TestGroupedTree::class)->instance();
    $flatItems = $instance->flatTreeItems;

    expect($flatItems)->toHaveCount(3);
    expect($flatItems[0]->key)->toBe('group:'.$root->id);
    expect($flatItems[1]->key)->toBe('group:'.$child->id);
    expect($flatItems[1]->ancestorKeys)->toBe(['group:'.$root->id]);
    expect($flatItems[2]->key)->toBe('leaf:'.$leaf->id);
    expect($flatItems[2]->ancestorKeys)->toBe(['group:'.$root->id, 'group:'.$child->id]);
});

it('reuses a single modal per form data class', function () {
    $root = CmsTestItem::factory()->create(['name' => 'Root', 'sort_order' => 0]);

    $instance = Livewire::test(TestGroupedTree::class)->instance();
    $modalNames = collect($instance->formModals)->pluck('name')->all();
    $leafAction = collect($instance->actions)->firstWhere('name', 'addLeafToGroup');

    expect($instance->formModals)->toHaveCount(2);
    expect($modalNames)->toContain('addLeaf-test-grouped');
    expect($instance->getModalNameForAction($leafAction))->toBe('addLeaf-test-grouped');

    Livewire::test(TestGroupedTree::class)
        ->call('openActionModal', 'addLeafToGroup', 'group:'.$root->id)
        ->assertDispatched('open-addLeaf-test-grouped');
});

it('routes shared modal submissions to the requested action handler', function () {
    $root = CmsTestItem::factory()->create(['name' => 'Root', 'sort_order' => 0]);

    Livewire::test(TestGroupedTree::class)
        ->call('handleFormActionSubmitted', [
            '_groupedTreeAction' => 'addLeafToGroup',
            'groupId' => $root->id,
            'name' => 'Leaf B',
        ]);

    expect(CmsTestLeaf::query()->where('name', 'Leaf B')->where('group_id', $root->id)->exists())->toBeTrue();
});
