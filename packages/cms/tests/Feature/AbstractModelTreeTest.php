<?php

use Coda\Cms\Tests\Fixtures\CmsTestItem;
use Coda\Cms\Tests\Fixtures\TestCardChildItemTree;
use Coda\Cms\Tests\Fixtures\TestManualSortItemTree;
use Coda\Cms\Tests\Fixtures\TestItemTree;
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

    Livewire::component('test-item-tree', TestItemTree::class);
    Livewire::component('test-manual-sort-item-tree', TestManualSortItemTree::class);
    Livewire::component('test-card-child-item-tree', TestCardChildItemTree::class);
});

// --- Setup & Rendering ---

it('mounts and renders root items', function () {
    CmsTestItem::factory()->create(['name' => 'Root A', 'sort_order' => 0]);
    CmsTestItem::factory()->create(['name' => 'Root B', 'sort_order' => 1]);

    $instance = Livewire::test(TestItemTree::class)->instance();
    $treeItems = $instance->treeItems;

    expect($treeItems)->toHaveCount(2);
    expect($treeItems[0]->name)->toBe('Root A');
    expect($treeItems[1]->name)->toBe('Root B');
});

it('loads nested children recursively', function () {
    $root = CmsTestItem::factory()->create(['name' => 'Root', 'sort_order' => 0]);
    $child = CmsTestItem::factory()->childOf($root)->create(['name' => 'Child', 'sort_order' => 0]);
    CmsTestItem::factory()->childOf($child)->create(['name' => 'Grandchild', 'sort_order' => 0]);

    $instance = Livewire::test(TestItemTree::class)->instance();
    $treeItems = $instance->treeItems;

    expect($treeItems)->toHaveCount(1);
    expect($treeItems[0]->name)->toBe('Root');
});

it('flattens tree with ancestor tracking', function () {
    $root = CmsTestItem::factory()->create(['name' => 'Root', 'sort_order' => 0]);
    $child = CmsTestItem::factory()->childOf($root)->create(['name' => 'Child', 'sort_order' => 0]);
    CmsTestItem::factory()->childOf($child)->create(['name' => 'Grandchild', 'sort_order' => 0]);

    $instance = Livewire::test(TestItemTree::class)->instance();
    $flatItems = $instance->flatTreeItems;

    expect($flatItems)->toHaveCount(3);
    expect($flatItems[0]->name)->toBe('Root');
    expect($flatItems[0]->ancestorIds)->toBe([]);
    expect($flatItems[0]->sortGroupKey)->toBe('root');
    expect($flatItems[1]->name)->toBe('Child');
    expect($flatItems[1]->ancestorIds)->toBe([$root->id]);
    expect($flatItems[1]->sortGroupKey)->toBe($root->id);
    expect($flatItems[2]->name)->toBe('Grandchild');
    expect($flatItems[2]->ancestorIds)->toBe([$root->id, $child->id]);
    expect($flatItems[2]->sortGroupKey)->toBe($child->id);
});

it('excludes children from root items', function () {
    $root = CmsTestItem::factory()->create(['name' => 'Root', 'sort_order' => 0]);
    CmsTestItem::factory()->childOf($root)->create(['name' => 'Child', 'sort_order' => 0]);

    $instance = Livewire::test(TestItemTree::class)->instance();

    expect($instance->treeItems)->toHaveCount(1);
});

it('marks first and last siblings', function () {
    CmsTestItem::factory()->create(['name' => 'First', 'sort_order' => 0]);
    CmsTestItem::factory()->create(['name' => 'Middle', 'sort_order' => 1]);
    CmsTestItem::factory()->create(['name' => 'Last', 'sort_order' => 2]);

    $instance = Livewire::test(TestItemTree::class)->instance();
    $flatItems = $instance->flatTreeItems;

    expect($flatItems[0]->isFirstSibling)->toBeTrue();
    expect($flatItems[0]->isLastSibling)->toBeFalse();
    expect($flatItems[1]->isFirstSibling)->toBeFalse();
    expect($flatItems[1]->isLastSibling)->toBeFalse();
    expect($flatItems[2]->isFirstSibling)->toBeFalse();
    expect($flatItems[2]->isLastSibling)->toBeTrue();
});

// --- Reordering ---

it('moves item up within siblings', function () {
    $a = CmsTestItem::factory()->create(['name' => 'A', 'sort_order' => 0]);
    $b = CmsTestItem::factory()->create(['name' => 'B', 'sort_order' => 1]);

    $instance = Livewire::test(TestItemTree::class)->instance();
    $instance->moveUp($b->id);

    expect($b->fresh()->sort_order)->toBe(0);
    expect($a->fresh()->sort_order)->toBe(1);
});

it('moves item down within siblings', function () {
    $a = CmsTestItem::factory()->create(['name' => 'A', 'sort_order' => 0]);
    $b = CmsTestItem::factory()->create(['name' => 'B', 'sort_order' => 1]);

    $instance = Livewire::test(TestItemTree::class)->instance();
    $instance->moveDown($a->id);

    expect($a->fresh()->sort_order)->toBe(1);
    expect($b->fresh()->sort_order)->toBe(0);
});

it('does not move first item up', function () {
    $a = CmsTestItem::factory()->create(['name' => 'A', 'sort_order' => 0]);

    $instance = Livewire::test(TestItemTree::class)->instance();
    $instance->moveUp($a->id);

    expect($a->fresh()->sort_order)->toBe(0);
});

it('does not move last item down', function () {
    $a = CmsTestItem::factory()->create(['name' => 'A', 'sort_order' => 0]);

    $instance = Livewire::test(TestItemTree::class)->instance();
    $instance->moveDown($a->id);

    expect($a->fresh()->sort_order)->toBe(0);
});

it('moves within same parent only', function () {
    $root1 = CmsTestItem::factory()->create(['name' => 'Root1', 'sort_order' => 0]);
    $root2 = CmsTestItem::factory()->create(['name' => 'Root2', 'sort_order' => 1]);
    $child1 = CmsTestItem::factory()->childOf($root1)->create(['name' => 'Child1', 'sort_order' => 0]);
    $child2 = CmsTestItem::factory()->childOf($root1)->create(['name' => 'Child2', 'sort_order' => 1]);

    $instance = Livewire::test(TestItemTree::class)->instance();
    $instance->moveUp($child2->id);

    expect($child2->fresh()->sort_order)->toBe(0);
    expect($child1->fresh()->sort_order)->toBe(1);
    expect($root2->fresh()->sort_order)->toBe(1);
});

it('reorders roots through manual sorting', function () {
    $a = CmsTestItem::factory()->create(['name' => 'A', 'sort_order' => 0]);
    $b = CmsTestItem::factory()->create(['name' => 'B', 'sort_order' => 1]);

    Livewire::test(TestManualSortItemTree::class)
        ->call('reorderTreeGroup', 'root', [$b->id, $a->id]);

    expect($b->fresh()->sort_order)->toBe(0);
    expect($a->fresh()->sort_order)->toBe(1);
});

it('reorders sibling children through manual sorting', function () {
    $root = CmsTestItem::factory()->create(['name' => 'Root', 'sort_order' => 0]);
    $first = CmsTestItem::factory()->childOf($root)->create(['name' => 'First', 'sort_order' => 0]);
    $second = CmsTestItem::factory()->childOf($root)->create(['name' => 'Second', 'sort_order' => 1]);

    Livewire::test(TestManualSortItemTree::class)
        ->call('reorderTreeGroup', (string) $root->id, [$second->id, $first->id]);

    expect($second->fresh()->sort_order)->toBe(0);
    expect($first->fresh()->sort_order)->toBe(1);
});

it('supports rendering shallow child cards when configured', function () {
    $root = CmsTestItem::factory()->create(['name' => 'Root', 'sort_order' => 0]);
    CmsTestItem::factory()->childOf($root)->create(['name' => 'Child', 'status' => 'Draft', 'sort_order' => 0]);

    $instance = Livewire::test(TestCardChildItemTree::class)->instance();
    $rootItem = $instance->treeItems[0];

    expect($instance->usesChildCards())->toBeTrue();
    expect($instance->shouldRenderChildCards($rootItem))->toBeTrue();
});

// --- Actions & CRUD ---

it('creates item via form submission', function () {
    Livewire::test(TestItemTree::class)
        ->call('handleFormSubmitted', [
            'name' => 'New Root',
            'status' => 'draft',
            'priority' => 0,
            'is_active' => true,
        ]);

    $item = CmsTestItem::where('name', 'New Root')->first();
    expect($item)->not->toBeNull();
    expect($item->sort_order)->toBe(0);
});

it('assigns sort_order to new items', function () {
    CmsTestItem::factory()->create(['name' => 'Existing', 'sort_order' => 0]);

    Livewire::test(TestItemTree::class)
        ->call('handleFormSubmitted', [
            'name' => 'Second',
            'status' => 'draft',
            'priority' => 0,
            'is_active' => true,
        ]);

    expect(CmsTestItem::where('name', 'Second')->first()->sort_order)->toBe(1);
});

it('updates item via form submission', function () {
    $item = CmsTestItem::factory()->create(['name' => 'Old']);

    Livewire::test(TestItemTree::class)
        ->call('handleFormSubmitted', [
            'id' => $item->id,
            'name' => 'Updated',
            'status' => 'draft',
            'priority' => 0,
            'is_active' => true,
        ]);

    expect($item->fresh()->name)->toBe('Updated');
});

it('deletes item', function () {
    $item = CmsTestItem::factory()->create(['name' => 'Doomed']);

    Livewire::test(TestItemTree::class)
        ->call('removeItem', $item->id);

    expect(CmsTestItem::find($item->id))->toBeNull();
});

it('deletes item after confirmation flow', function () {
    $item = CmsTestItem::factory()->create(['name' => 'Doomed']);

    Livewire::test(TestItemTree::class)
        ->call('confirmAction', 'delete', $item->id)
        ->assertSet('confirmingId', $item->id)
        ->assertSet('confirmingAction', 'delete')
        ->call('executeConfirmedAction');

    expect(CmsTestItem::find($item->id))->toBeNull();
});
