<?php

namespace Coda\Cms;

use Coda\Cms\Data\AbstractData;
use Coda\Cms\Navigation\SidebarGroup;
use Coda\Cms\Navigation\SidebarItem;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Area extends AbstractData
{
    /**
     * @param Module[] $modules
     * @param Area[] $children
     */
    public function __construct(
        public string $key = '',
        public string $label = '',
        public string $icon = '',
        public ?string $dashboardRoute = null,
        public string $dashboardLabel = 'Overview',
        public ?string $prefix = null,
        public ?string $scopeParam = null,
        public string $scopeRouteKey = 'slug',
        public $scopeResolver = null,
        public $scopeDefaultResolver = null,
        public array $modules = [],
        public array $children = [],
    ) {}

    public static function make(string $key, ?string $label = null): static
    {
        return new static(
            key: $key,
            label: $label ?? Str::of($key)->replace(['-', '_'], ' ')->headline()->toString(),
        );
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function dashboard(string $route, string $label = 'Overview'): static
    {
        $this->dashboardRoute = $route;
        $this->dashboardLabel = $label;

        return $this;
    }

    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function scope(
        string $param,
        callable $resolver,
        string $routeKey = 'slug',
        ?callable $default = null,
    ): static {
        $this->scopeParam = $param;
        $this->scopeResolver = $resolver;
        $this->scopeRouteKey = $routeKey;
        $this->scopeDefaultResolver = $default;

        return $this;
    }

    /** @param Module[] $modules */
    public function modules(array $modules): static
    {
        $this->modules = $modules;

        return $this;
    }

    /** @param Area[] $children */
    public function children(array $children): static
    {
        $this->children = $children;

        return $this;
    }

    /** @return Module[] */
    public function allModules(): array
    {
        return [
            ...$this->modules,
            ...collect($this->children)
                ->flatMap(fn (Area $area) => $area->allModules())
                ->all(),
        ];
    }

    public function toNavigationGroup(): SidebarGroup
    {
        return SidebarGroup::make($this->key, $this->label)
            ->icon($this->icon)
            ->items($this->navigationItems());
    }

    public function toNavigationItem(): SidebarItem
    {
        return SidebarItem::make($this->label)
            ->icon($this->icon)
            ->items($this->navigationItems());
    }

    public function isScoped(): bool
    {
        return $this->scopeParam !== null && is_callable($this->scopeResolver);
    }

    public function routePath(string $route): string
    {
        if ($this->isAbsoluteRoute($route)) {
            return $this->normalizePath($route);
        }

        $segments = [];

        if ($this->prefix) {
            $segments[] = trim($this->prefix, '/');
        }

        if ($this->isScoped()) {
            $segments[] = sprintf('{%s:%s}', $this->scopeParam, $this->scopeRouteKey);
        }

        $segments[] = trim($route, '/');

        return '/'.collect($segments)
            ->filter(fn (string $segment) => $segment !== '')
            ->implode('/');
    }

    public function navigationRoute(): ?string
    {
        if ($this->dashboardRoute !== null) {
            return $this->dashboardRoute;
        }

        foreach ($this->children as $child) {
            $route = $child->navigationRoute();

            if ($route !== null) {
                return $route;
            }
        }

        foreach ($this->modules as $module) {
            $route = $module->navigationRoute();

            if ($route !== null) {
                return $route;
            }
        }

        return null;
    }

    public function matchesRoute(?string $routeName): bool
    {
        if ($routeName === null) {
            return false;
        }

        if ($this->dashboardRoute === $routeName) {
            return true;
        }

        foreach ($this->children as $child) {
            if ($child->matchesRoute($routeName)) {
                return true;
            }
        }

        foreach ($this->modules as $module) {
            if ($module->navigationRoute() === $routeName) {
                return true;
            }

            foreach ($module->pages() as $page) {
                if ($page->name === $routeName) {
                    return true;
                }
            }

            foreach ($module->detailPages() as $page) {
                if ($page->name === $routeName) {
                    return true;
                }
            }
        }

        return false;
    }

    public function resolveCurrentContext(mixed $value = null): mixed
    {
        if (! $this->isScoped()) {
            return null;
        }

        $value ??= request()->route($this->scopeParam);

        if ($value instanceof Model) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        $contexts = $this->resolveContextsSource();

        if ($contexts instanceof Relation || $contexts instanceof EloquentBuilder) {
            return (clone $contexts)
                ->where($this->scopeRouteKey, $value)
                ->first();
        }

        return collect($contexts)->firstWhere($this->scopeRouteKey, $value);
    }

    public function resolveDefaultContext(): mixed
    {
        if (! $this->isScoped()) {
            return null;
        }

        if (is_callable($this->scopeDefaultResolver)) {
            return ($this->scopeDefaultResolver)();
        }

        $contexts = $this->resolveContextsSource();

        if ($contexts instanceof Relation || $contexts instanceof EloquentBuilder) {
            return (clone $contexts)->first();
        }

        return collect($contexts)->first();
    }

    public function contextRouteValue(mixed $context = null): mixed
    {
        if (! $this->isScoped()) {
            return null;
        }

        if ($context instanceof Model) {
            return $context->getAttribute($this->scopeRouteKey);
        }

        if (is_object($context)) {
            return data_get($context, $this->scopeRouteKey);
        }

        return $context;
    }

    /** @return SidebarGroup[] */
    public function sidebarGroups(): array
    {
        $groups = [];
        $primaryItems = $this->moduleNavigationItems();

        if ($primaryItems !== []) {
            $groups[] = SidebarGroup::make($this->key, '')
                ->items($primaryItems);
        }

        foreach ($this->children as $child) {
            $group = $child->toNavigationGroup();

            if ($group->items !== []) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /** @return SidebarItem[] */
    public function navigationItems(): array
    {
        $items = $this->moduleNavigationItems();

        foreach ($this->children as $child) {
            $items[] = $child->toNavigationItem();
        }

        return $items;
    }

    /** @return SidebarItem[] */
    protected function moduleNavigationItems(): array
    {
        $items = [];

        if ($this->dashboardRoute !== null) {
            $items[] = SidebarItem::make($this->dashboardLabel, $this->dashboardRoute)
                ->icon($this->icon);
        }

        foreach ($this->modules as $module) {
            $item = $module->navigationItem();

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    protected function resolveContextsSource(): Relation|EloquentBuilder|Collection|array
    {
        $contexts = ($this->scopeResolver)();

        if ($contexts instanceof Relation || $contexts instanceof EloquentBuilder || $contexts instanceof Collection || is_array($contexts)) {
            return $contexts;
        }

        return collect($contexts);
    }

    protected function isAbsoluteRoute(string $route): bool
    {
        return str_starts_with($route, '/');
    }

    protected function normalizePath(string $route): string
    {
        return '/'.ltrim($route, '/');
    }
}
