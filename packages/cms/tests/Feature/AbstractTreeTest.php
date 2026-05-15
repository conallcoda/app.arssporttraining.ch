<?php

use Coda\Cms\Tests\Fixtures\TestStateTree;
use Livewire\Livewire;

beforeEach(function () {
    Livewire::component('test-state-tree', TestStateTree::class);
});

it('builds a tree from in-memory array state', function () {
    $instance = Livewire::test(TestStateTree::class)->instance();

    expect($instance->treeItems)->toHaveCount(2);
    expect($instance->treeItems[0]->name)->toBe('Alpha');
    expect($instance->treeItems[0]->children)->toHaveCount(1);
    expect($instance->treeItems[1]->key)->toBe('beta');
});

it('flattens array-backed trees with ancestor key tracking', function () {
    $instance = Livewire::test(TestStateTree::class)->instance();
    $flatItems = $instance->flatTreeItems;

    expect($flatItems)->toHaveCount(3);
    expect($flatItems[0]->key)->toBe('alpha');
    expect($flatItems[1]->key)->toBe('alpha-1');
    expect($flatItems[1]->ancestorKeys)->toBe(['alpha']);
    expect($flatItems[2]->isLastSibling)->toBeTrue();
});
