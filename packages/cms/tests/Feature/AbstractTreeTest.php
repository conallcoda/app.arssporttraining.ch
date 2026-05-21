<?php

use Coda\Cms\Tests\Fixtures\TestExpandedStateTree;
use Coda\Cms\Tests\Fixtures\TestHiddenExpandControlsStateTree;
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
    expect($instance->treeItems[0]->children[0]->children)->toHaveCount(1);
    expect($instance->treeItems[1]->key)->toBe('beta');
});

it('flattens array-backed trees with ancestor key tracking', function () {
    $instance = Livewire::test(TestStateTree::class)->instance();
    $flatItems = $instance->flatTreeItems;

    expect($flatItems)->toHaveCount(4);
    expect($flatItems[0]->key)->toBe('alpha');
    expect($flatItems[1]->key)->toBe('alpha-1');
    expect($flatItems[2]->key)->toBe('alpha-1-a');
    expect($flatItems[2]->ancestorKeys)->toBe(['alpha', 'alpha-1']);
    expect($flatItems[1]->ancestorKeys)->toBe(['alpha']);
    expect($flatItems[3]->isLastSibling)->toBeTrue();
});

it('computes expandable keys and default expanded keys from tree config', function () {
    $instance = Livewire::test(TestExpandedStateTree::class)->instance();

    expect($instance->expandableTreeKeys)->toBe(['alpha', 'alpha-1']);
    expect($instance->defaultExpandedTreeKeys)->toBe(['alpha', 'alpha-1']);
});

it('renders expand and collapse controls when enabled for expandable trees', function () {
    Livewire::test(TestStateTree::class)
        ->assertSeeHtml('aria-label="Collapse all"')
        ->assertSeeHtml('aria-label="Expand all"');
});

it('can hide expand and collapse controls per tree configuration', function () {
    Livewire::test(TestHiddenExpandControlsStateTree::class)
        ->assertDontSeeHtml('aria-label="Collapse all"')
        ->assertDontSeeHtml('aria-label="Expand all"');
});
