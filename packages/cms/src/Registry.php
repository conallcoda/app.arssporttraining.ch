<?php

namespace Coda\Cms;

use Coda\Cms\Navigation\SidebarGroup;
use Coda\Cms\Navigation\SidebarItem;
use Coda\Cms\Navigation\Tab;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class Registry
{
    /** @var array<string, Module> */
    protected array $modules = [];

    /** @var array<string, Area> */
    protected array $areas = [];

    /** @var SidebarGroup[] */
    protected array $navigation = [];

    public function register(Module $module): void
    {
        $this->modules[$module->name()] = $module;
    }

    public function registerArea(Area $area): void
    {
        $this->areas[$area->key] = $area;
    }

    /** @param SidebarGroup[] $groups */
    public function setNavigation(array $groups): void
    {
        $this->navigation = $groups;
    }

    /** @return Collection<int, Area> */
    public function areas(): Collection
    {
        return collect($this->areas)->values();
    }

    /** @return Collection<int, Module> */
    public function modules(): Collection
    {
        return collect([
            ...array_values($this->modules),
            ...$this->areas()->flatMap(fn (Area $area) => $area->allModules())->all(),
        ])->values();
    }

    /** @return Collection<int, PageDefinition> */
    public function pages(): Collection
    {
        return $this->modules()
            ->flatMap(fn (Module $module) => [
                ...$module->pages(),
                ...$module->detailPages(),
            ])
            ->values();
    }

    public function page(string $name): ?PageDefinition
    {
        return $this->pages()->firstWhere('name', $name);
    }

    public function pageArea(string $routeName): ?Area
    {
        return $this->areas()
            ->first(fn (Area $area) => $area->matchesRoute($routeName));
    }

    public function area(string $key): ?Area
    {
        return $this->areas[$key] ?? null;
    }

    /** @return Collection<int, ComponentDefinition> */
    public function components(): Collection
    {
        return $this->modules()
            ->flatMap(fn (Module $module) => $module->components())
            ->values();
    }

    public function component(string $name): ?ComponentDefinition
    {
        return $this->components()->firstWhere('name', $name);
    }

    public function moduleForPage(string $pageName): ?Module
    {
        return $this->modules()->first(function (Module $module) use ($pageName): bool {
            foreach ($module->pages() as $page) {
                if ($page->name === $pageName) {
                    return true;
                }
            }

            foreach ($module->detailPages() as $page) {
                if ($page->name === $pageName) {
                    return true;
                }
            }

            return false;
        });
    }

    /** @return SidebarGroup[] */
    public function navigation(?string $routeName = null): array
    {
        if ($this->usesAreaNavigation()) {
            $area = $this->currentArea($routeName);

            if ($area !== null) {
                return $area->sidebarGroups();
            }

            return [];
        }

        if ($this->navigation !== []) {
            return $this->navigation;
        }

        if ($this->areas !== []) {
            return $this->areas()
                ->map(fn (Area $area) => $area->toNavigationGroup())
                ->all();
        }

        return $this->modules()
            ->map(function (Module $module) {
                $item = $module->navigationItem();

                if ($item === null) {
                    return null;
                }

                return SidebarGroup::make($module->name(), $module->label())
                    ->icon($module->icon())
                    ->items([$item]);
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return Collection<int, Area> */
    public function topNavigationAreas(): Collection
    {
        if (! $this->usesAreaNavigation()) {
            return collect();
        }

        return $this->areas()
            ->filter(fn (Area $area) => $this->urlForArea($area) !== null)
            ->values();
    }

    public function currentArea(?string $routeName = null): ?Area
    {
        if (! $this->usesAreaNavigation()) {
            return null;
        }

        $routeName ??= request()->route()?->getName();

        return $this->areas()
            ->first(fn (Area $area) => $area->matchesRoute($routeName));
    }

    public function currentContext(?string $routeName = null): mixed
    {
        $area = $routeName !== null
            ? $this->pageArea($routeName)
            : $this->currentArea();

        if (! $area?->isScoped()) {
            return null;
        }

        $currentArea = $this->currentArea();

        if ($currentArea?->key !== $area->key) {
            return null;
        }

        return $area->resolveCurrentContext();
    }

    public function currentContextValue(string $path = 'id', mixed $default = null, ?string $routeName = null): mixed
    {
        $context = $this->currentContext($routeName);

        if ($context instanceof \Illuminate\Database\Eloquent\Model && ($path === 'id' || $path === $context->getKeyName())) {
            return $context->getKey();
        }

        return data_get($context, $path, $default);
    }

    /**
     * @return array<string, array{areaKey: string, routeValue: mixed, routeKey: string}>
     */
    public function activeScopeBindings(?string $routeName = null): array
    {
        $area = $routeName !== null
            ? $this->pageArea($routeName)
            : $this->currentArea();

        if (! $area?->isScoped()) {
            return [];
        }

        $context = $routeName !== null
            ? $area->resolveCurrentContext()
            : $this->currentContext();

        if ($context === null) {
            return [];
        }

        return [
            $area->scopeParam => [
                'areaKey' => $area->key,
                'routeValue' => $area->contextRouteValue($context),
                'routeKey' => $area->scopeRouteKey,
            ],
        ];
    }

    public function currentScopeBindingContext(string $binding, ?string $routeName = null): mixed
    {
        $bindings = $this->activeScopeBindings($routeName);
        $metadata = $bindings[$binding] ?? null;

        if (! is_array($metadata) || ! is_string($metadata['areaKey'] ?? null)) {
            return null;
        }

        $area = $this->area($metadata['areaKey']);

        if (! $area?->isScoped()) {
            return null;
        }

        return $area->resolveCurrentContext($metadata['routeValue'] ?? null);
    }

    public function currentScopeBindingValue(string $binding, string $path = 'id', mixed $default = null, ?string $routeName = null): mixed
    {
        $context = $this->currentScopeBindingContext($binding, $routeName);

        if ($context instanceof \Illuminate\Database\Eloquent\Model && ($path === 'id' || $path === $context->getKeyName())) {
            return $context->getKey();
        }

        return data_get($context, $path, $default);
    }

    public function urlForArea(Area $area): ?string
    {
        $routeName = $area->navigationRoute();

        if ($routeName === null) {
            return null;
        }

        $parameters = [];

        if ($area->isScoped()) {
            $defaultContext = $area->resolveDefaultContext();
            $defaultValue = $area->contextRouteValue($defaultContext);

            if ($defaultValue === null || $defaultValue === '') {
                return null;
            }

            $parameters[$area->scopeParam] = $defaultValue;
        }

        return route($routeName, $parameters);
    }

    public function urlForRoute(string $routeName, array $parameters = []): string
    {
        return route($routeName, $this->parametersForRoute($routeName, $parameters));
    }

    public function parametersForRoute(string $routeName, array $parameters = []): array
    {
        $area = $this->pageArea($routeName);

        if (! $area?->isScoped()) {
            return $parameters;
        }

        if (! array_key_exists($area->scopeParam, $parameters)) {
            $context = $this->currentContext($routeName) ?? $area->resolveDefaultContext();
            $contextValue = $area->contextRouteValue($context);

            if ($contextValue !== null && $contextValue !== '') {
                $parameters[$area->scopeParam] = $contextValue;
            }
        }

        return $parameters;
    }

    /** @return Tab[] */
    public function tabsForRoute(string $routeName): array
    {
        foreach ($this->navigation($routeName) as $group) {
            $tabs = $this->tabsForItems($group->items, $routeName);

            if ($tabs !== []) {
                return $tabs;
            }
        }

        return [];
    }

    /** @param SidebarItem[] $items */
    protected function tabsForItems(array $items, string $routeName): array
    {
        foreach ($items as $item) {
            foreach ($item->tabs as $tab) {
                if ($tab->route === $routeName) {
                    return $item->tabs;
                }
            }

            if ($item->items !== []) {
                $tabs = $this->tabsForItems($item->items, $routeName);

                if ($tabs !== []) {
                    return $tabs;
                }
            }
        }

        return [];
    }

    public function registerRoutes(): void
    {
        foreach ($this->pages() as $page) {
            $route = Route::get($this->routePathForPage($page), \Coda\Cms\Livewire\CmsPage::class)
                ->name($page->name);

            if ($page->middleware) {
                $route->middleware($page->middleware);
            }
        }
    }

    protected function usesAreaNavigation(): bool
    {
        return config('cms.area_navigation.enabled', false) && $this->areas !== [];
    }

    protected function routePathForPage(PageDefinition $page): string
    {
        $area = $this->pageArea($page->name);

        if ($area === null) {
            return '/'.ltrim($page->route, '/');
        }

        return $area->routePath($page->route);
    }
}
