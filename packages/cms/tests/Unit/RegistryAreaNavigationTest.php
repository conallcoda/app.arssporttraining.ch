<?php

use Coda\Cms\Area;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;
use Coda\Cms\Registry;

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('cms.area_navigation.enabled', true);
});

it('uses the current area to build sidebar navigation', function () {
    $registry = new Registry;
    $registry->registerArea(
        Area::make('conference', 'Conference')->children([
            Area::make('planning', 'Planning')->modules([
                new TestAreaModule('programs', 'Programs', 'program-index'),
                new TestAreaModule('calendar', 'Calendar', 'calendar-index'),
            ]),
        ]),
    );
    $registry->registerArea(
        Area::make('admin', 'Admin')->children([
            Area::make('people', 'People')->modules([
                new TestAreaModule('coaches', 'Coaches', 'coach-index'),
                new TestAreaModule('athletes', 'Athletes', 'athlete-index'),
            ]),
        ]),
    );

    $navigation = $registry->navigation('calendar-index');

    expect($navigation)->toHaveCount(1)
        ->and($navigation[0]->heading)->toBe('Planning')
        ->and(collect($navigation[0]->items)->pluck('label')->all())->toBe(['Programs', 'Calendar']);
});

it('does not repeat the active area label as a sidebar group heading', function () {
    $registry = new Registry;
    $registry->registerArea(
        Area::make('conference', 'Conference')->modules([
            new TestAreaModule('people', 'People', 'conference.people'),
            new TestAreaModule('companies', 'Companies', 'conference.companies'),
        ]),
    );

    $navigation = $registry->navigation('conference.people');

    expect($navigation)->toHaveCount(1)
        ->and($navigation[0]->heading)->toBe('')
        ->and(collect($navigation[0]->items)->pluck('label')->all())->toBe(['People', 'Companies']);
});

it('resolves the active top navigation area from nested module routes', function () {
    $registry = new Registry;
    $registry->registerArea(
        Area::make('conference', 'Conference')->children([
            Area::make('planning', 'Planning')->modules([
                new TestAreaModule('programs', 'Programs', 'program-index'),
            ]),
        ]),
    );
    $registry->registerArea(
        Area::make('tools', 'Tools')->children([
            Area::make('library', 'Library')->modules([
                new TestAreaModule('exercises', 'Exercises', 'exercise-index'),
            ]),
        ]),
    );

    $area = $registry->currentArea('exercise-index');

    expect($area)->not->toBeNull()
        ->and($area?->key)->toBe('tools');
});

it('keeps top navigation empty when the feature is disabled', function () {
    config()->set('cms.area_navigation.enabled', false);

    $registry = new Registry;
    $registry->registerArea(
        Area::make('conference', 'Conference')->modules([
            new TestAreaModule('calendar', 'Calendar', 'calendar-index'),
        ]),
    );

    expect($registry->topNavigationAreas())->toHaveCount(0)
        ->and($registry->currentArea('calendar-index'))->toBeNull();
});

it('builds scoped area paths and default route parameters from relative routes', function () {
    $contexts = collect([
        (object) ['slug' => 'grisons-switzerland-2026'],
        (object) ['slug' => 'grisons-switzerland-2025'],
    ]);

    $registry = new Registry;
    $registry->registerArea(
        Area::make('conference', 'Conference')
            ->prefix('/admin/conference')
            ->scope(
                param: 'edition',
                resolver: fn () => $contexts,
                routeKey: 'slug',
                default: fn () => $contexts->first(),
            )
            ->modules([
                new TestAreaModule('conference-editions', 'Editions', 'scoped.editions', 'editions'),
                new TestAreaModule('conference-attendees', 'Attendees', 'scoped.attendees', 'attendees'),
            ]),
    );

    expect($registry->areas()->first()->routePath('editions'))
        ->toBe('/admin/conference/{edition:slug}/editions')
        ->and($registry->parametersForRoute('scoped.attendees'))
        ->toBe(['edition' => 'grisons-switzerland-2026']);
});

class TestAreaModule extends Module
{
    public function __construct(
        protected string $moduleName,
        protected string $moduleLabel,
        protected string $routeName,
        protected ?string $routePath = null,
    ) {}

    public function name(): string
    {
        return $this->moduleName;
    }

    public function label(): string
    {
        return $this->moduleLabel;
    }

    public function pages(): array
    {
        return [
            PageDefinition::make($this->routeName)
                ->route($this->routePath ?? '/'.str_replace('_', '-', $this->routeName)),
        ];
    }
}
