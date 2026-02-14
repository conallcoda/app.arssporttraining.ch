<?php

namespace Coda\Cms;

use Coda\Cms\Navigation\SidebarGroup;
use Coda\Cms\Navigation\Tab;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class Registry
{
    /** @var array<string, Module> */
    protected array $modules = [];

    /** @var SidebarGroup[] */
    protected array $navigation = [];

    public function register(Module $module): void
    {
        $this->modules[$module->name()] = $module;
    }

    /** @param SidebarGroup[] $groups */
    public function setNavigation(array $groups): void
    {
        $this->navigation = $groups;
    }

    /** @return Collection<int, PageDefinition> */
    public function pages(): Collection
    {
        return collect($this->modules)
            ->flatMap(fn (Module $module) => $module->pages())
            ->values();
    }

    public function page(string $name): ?PageDefinition
    {
        return $this->pages()->firstWhere('name', $name);
    }

    /** @return Collection<int, ComponentDefinition> */
    public function components(): Collection
    {
        return collect($this->modules)
            ->flatMap(fn (Module $module) => $module->components())
            ->values();
    }

    public function component(string $name): ?ComponentDefinition
    {
        return $this->components()->firstWhere('name', $name);
    }

    /** @return SidebarGroup[] */
    public function navigation(): array
    {
        return $this->navigation;
    }

    /** @return Tab[] */
    public function tabsForRoute(string $routeName): array
    {
        foreach ($this->navigation as $group) {
            foreach ($group->items as $item) {
                foreach ($item->tabs as $tab) {
                    if ($tab->route === $routeName) {
                        return $item->tabs;
                    }
                }
            }
        }

        return [];
    }

    public function registerRoutes(): void
    {
        foreach ($this->pages() as $page) {
            if ($page->isCustom()) {
                $route = Route::get($page->route, $page->component)
                    ->name($page->name);
            } else {
                $route = Route::get($page->route, \Coda\Cms\Livewire\CmsPage::class)
                    ->name($page->name);
            }

            if ($page->middleware) {
                $route->middleware($page->middleware);
            }
        }
    }
}
