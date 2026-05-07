<?php

use Coda\Cms\Tests\Fixtures\CmsTestItem;
use Coda\Cms\Tests\Fixtures\TestCardEnabledList;
use Coda\Cms\Tests\Fixtures\TestInfiniteList;
use Coda\Cms\Tests\Fixtures\TestItemList;
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

    Livewire::component('test-item-list', TestItemList::class);
    Livewire::component('test-infinite-list', TestInfiniteList::class);
    Livewire::component('test-card-enabled-list', TestCardEnabledList::class);
});

// --- Setup & Rendering ---

it('mounts and renders items', function () {
    CmsTestItem::factory()->create(['name' => 'Alpha']);
    CmsTestItem::factory()->create(['name' => 'Beta']);

    Livewire::test(TestItemList::class)
        ->assertOk()
        ->assertSee('Alpha')
        ->assertSee('Beta');
});

it('paginates items', function () {
    CmsTestItem::factory(25)->create();

    $items = Livewire::test(TestItemList::class)->instance()->items();

    expect($items)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
});

it('returns columns from table config', function () {
    $columns = Livewire::test(TestItemList::class)->instance()->columns;

    expect($columns)->toHaveCount(3);
});

// --- Sorting ---

it('sorts by default column on mount', function () {
    CmsTestItem::factory()->create(['name' => 'Banana']);
    CmsTestItem::factory()->create(['name' => 'Apple']);

    $items = Livewire::test(TestItemList::class)->instance()->items();
    $names = $items->pluck('name')->all();

    expect($names[0])->toBe('Apple');
    expect($names[1])->toBe('Banana');
});

it('toggles sort direction when clicking same column', function () {
    CmsTestItem::factory()->create(['name' => 'Apple']);
    CmsTestItem::factory()->create(['name' => 'Banana']);

    $instance = Livewire::test(TestItemList::class)->instance();
    $instance->sortBy('name');

    expect($instance->sort)->toBe('-name');
});

it('switches sort column', function () {
    $instance = Livewire::test(TestItemList::class)->instance();
    $instance->sortBy('priority');

    expect($instance->sort)->toBe('priority');
});

it('ignores sort on non-sortable column', function () {
    $instance = Livewire::test(TestItemList::class)->instance();
    $instance->sortBy('status');

    expect($instance->sort)->toBe('');
});

it('reports sort direction correctly', function () {
    $instance = Livewire::test(TestItemList::class)->instance();

    expect($instance->currentSortField())->toBe('name');
    expect($instance->currentSortDirection())->toBe('asc');
    expect($instance->isSortedBy('name'))->toBeTrue();

    $instance->sortBy('name');
    expect($instance->currentSortDirection())->toBe('desc');
});

// --- Filtering ---

it('filters by exact status value', function () {
    CmsTestItem::factory()->create(['name' => 'Draft Item', 'status' => 'draft']);
    CmsTestItem::factory()->published()->create(['name' => 'Published Item']);

    $component = Livewire::test(TestItemList::class)
        ->set('filters.status', 'published')
        ->call('applyFilters');

    $items = $component->instance()->items();
    expect($items->total())->toBe(1);
    expect($items->first()->name)->toBe('Published Item');
});

it('filters by search callback', function () {
    CmsTestItem::factory()->create(['name' => 'Strength Training']);
    CmsTestItem::factory()->create(['name' => 'Cardio Workout']);

    $component = Livewire::test(TestItemList::class)
        ->set('filters.search', 'Strength')
        ->call('applyFilters');

    $items = $component->instance()->items();
    expect($items->total())->toBe(1);
    expect($items->first()->name)->toBe('Strength Training');
});

it('clears a single filter', function () {
    CmsTestItem::factory()->create(['status' => 'draft']);
    CmsTestItem::factory()->published()->create();

    $component = Livewire::test(TestItemList::class)
        ->set('filters.status', 'published')
        ->call('applyFilters');

    expect($component->instance()->items()->total())->toBe(1);

    $component->call('clearFilter', 'status');

    expect($component->instance()->items()->total())->toBe(2);
});

it('clears all filters', function () {
    CmsTestItem::factory()->create(['status' => 'draft']);
    CmsTestItem::factory()->published()->create();

    $component = Livewire::test(TestItemList::class)
        ->set('filters.status', 'published')
        ->set('filters.search', 'test')
        ->call('applyFilters')
        ->call('clearFilters');

    expect($component->instance()->hasActiveFilters())->toBeFalse();
    expect($component->instance()->items()->total())->toBe(2);
});

// --- Actions & CRUD ---

it('has add action in header actions', function () {
    $headerActions = Livewire::test(TestItemList::class)->instance()->headerActions;

    expect($headerActions)->toHaveCount(1);
    expect($headerActions[0]->name)->toBe('add');
});

it('has edit and delete actions on rows', function () {
    $rowActions = Livewire::test(TestItemList::class)->instance()->rowActions;
    $names = collect($rowActions)->pluck('name')->all();

    expect($names)->toContain('edit');
    expect($names)->toContain('delete');
});

it('creates an item via form submission event', function () {
    Livewire::test(TestItemList::class)
        ->call('handleFormSubmitted', [
            'name' => 'New Item',
            'status' => 'draft',
            'priority' => 0,
            'is_active' => true,
        ]);

    expect(CmsTestItem::where('name', 'New Item')->exists())->toBeTrue();
});

it('updates an existing item via form submission event', function () {
    $item = CmsTestItem::factory()->create(['name' => 'Old Name']);

    Livewire::test(TestItemList::class)
        ->call('handleFormSubmitted', [
            'id' => $item->id,
            'name' => 'New Name',
            'status' => 'draft',
            'priority' => 0,
            'is_active' => true,
        ]);

    expect($item->fresh()->name)->toBe('New Name');
});

it('deletes an item after confirmation', function () {
    $item = CmsTestItem::factory()->create(['name' => 'Doomed']);

    Livewire::test(TestItemList::class)
        ->call('confirmAction', 'delete', $item->id)
        ->assertSet('confirmingId', $item->id)
        ->assertSet('confirmingAction', 'delete')
        ->call('executeConfirmedAction');

    expect(CmsTestItem::find($item->id))->toBeNull();
});

it('opens edit from URL parameter on mount', function () {
    $item = CmsTestItem::factory()->create(['name' => 'Editable']);

    $component = Livewire::test(TestItemList::class, ['edit' => $item->id]);
    $editModalName = $component->instance()->editModalName;

    $component->assertDispatched("open-{$editModalName}", function ($event, $params) use ($item) {
        return ($params['data']['id'] ?? null) === $item->id;
    });
});

// --- View Mode (opt-out: cards not enabled) ---

it('initialises view mode to table by default', function () {
    $instance = Livewire::test(TestItemList::class)->instance();

    expect($instance->viewMode)->toBe('table');
});

it('reports cardsEnabled as false when cards() was not declared', function () {
    $instance = Livewire::test(TestItemList::class)->instance();

    expect($instance->cardsEnabled)->toBeFalse();
});

it('returns empty cards array when cards are not enabled', function () {
    $cards = Livewire::test(TestItemList::class)->instance()->cards;

    expect($cards)->toBe([]);
});

it('ignores setView(cards) when cards are not enabled', function () {
    Livewire::test(TestItemList::class)
        ->call('setView', 'cards')
        ->assertSet('viewMode', 'table');
});

it('ignores invalid view modes', function () {
    Livewire::test(TestItemList::class)
        ->call('setView', 'unknown')
        ->assertSet('viewMode', 'table');
});

// --- View Mode (opt-in: cards enabled) ---

it('reports cardsEnabled as true when cards() is declared', function () {
    $instance = Livewire::test(TestCardEnabledList::class)->instance();

    expect($instance->cardsEnabled)->toBeTrue();
});

it('switches to card view via setView when cards are enabled', function () {
    Livewire::test(TestCardEnabledList::class)
        ->call('setView', 'cards')
        ->assertSet('viewMode', 'cards');
});

it('exposes declared card fields via computed property', function () {
    $cards = Livewire::test(TestCardEnabledList::class)->instance()->cards;

    expect($cards)->toHaveCount(2);
});

it('exposes cardLayout as computed property', function () {
    $instance = Livewire::test(TestCardEnabledList::class)->instance();

    expect($instance->cardLayout)->toBe('grid');
});

it('exposes cardWidth as computed property', function () {
    $instance = Livewire::test(TestCardEnabledList::class)->instance();

    expect($instance->cardWidth)->toBe(260);
});

// --- Pagination: Load More / Infinite / Hybrid (accumulation) ---

it('defaults loadedPages to 1 on mount', function () {
    $instance = Livewire::test(TestInfiniteList::class)->instance();

    expect($instance->loadedPages)->toBe(1);
});

it('loadMore is a no-op under classic pagination', function () {
    $component = Livewire::test(TestItemList::class)->call('loadMore');

    expect($component->instance()->loadedPages)->toBe(1);
});

it('loadMore increments loadedPages under an accumulating strategy', function () {
    CmsTestItem::factory(15)->create();

    $component = Livewire::test(TestInfiniteList::class)
        ->call('loadMore');

    expect($component->instance()->loadedPages)->toBe(2);
});

it('accumulates items across loadMore calls and reports hasMorePages correctly', function () {
    CmsTestItem::factory(25)->create();

    $component = Livewire::test(TestInfiniteList::class);

    $items = $component->instance()->items();
    expect($items->count())->toBe(10);
    expect($items->hasMorePages())->toBeTrue();

    $component->call('loadMore');
    $items = $component->instance()->items();
    expect($items->count())->toBe(20);
    expect($items->hasMorePages())->toBeTrue();

    $component->call('loadMore');
    $items = $component->instance()->items();
    expect($items->count())->toBe(25);
    expect($items->hasMorePages())->toBeFalse();
});

it('resets loadedPages to 1 when filters change', function () {
    CmsTestItem::factory(25)->create();

    $component = Livewire::test(TestInfiniteList::class)
        ->call('loadMore')
        ->call('loadMore');

    expect($component->instance()->loadedPages)->toBe(3);

    $component->call('clearFilters');

    expect($component->instance()->loadedPages)->toBe(1);
});

// --- Entity Helpers ---

it('generates entity slug and name', function () {
    $instance = Livewire::test(TestItemList::class)->instance();

    expect($instance->editModalName)->toBeString()->not->toBeEmpty();
    expect($instance->headerActions[0]->label)->toContain('Add');
});
