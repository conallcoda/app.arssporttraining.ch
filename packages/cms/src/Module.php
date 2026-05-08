<?php

namespace Coda\Cms;

use Coda\Cms\Navigation\SidebarItem;
use Illuminate\Support\Str;

abstract class Module
{
    abstract public function name(): string;

    /** @return PageDefinition[] */
    abstract public function pages(): array;

    /** @return DetailsPageDefinition[] */
    public function detailPages(): array
    {
        return [];
    }

    /** @return ComponentDefinition[] */
    public function components(): array
    {
        return [];
    }

    public function label(): string
    {
        return Str::of($this->name())
            ->replace(['-', '_'], ' ')
            ->headline()
            ->toString();
    }

    public function icon(): string
    {
        return '';
    }

    public function navigationRoute(): ?string
    {
        $pages = $this->pages();

        return $pages[0]->name ?? null;
    }

    /** @return array<int, \Coda\Cms\Navigation\Tab> */
    public function tabs(): array
    {
        return [];
    }

    public function navigationItem(): ?SidebarItem
    {
        $route = $this->navigationRoute();

        if ($route === null) {
            return null;
        }

        return SidebarItem::make($this->label(), $route)
            ->icon($this->icon())
            ->tabs($this->tabs());
    }

    protected function detailsPage(
        string $name,
        string $route,
        string $listComponent,
        ?string $component = null,
    ): DetailsPageDefinition {
        return DetailsPageDefinition::make($name)
            ->route($route)
            ->title($this->label().' Details')
            ->component($component ?? \Coda\Cms\Livewire\ModelDetailsPage::class)
            ->listComponent($listComponent);
    }
}
