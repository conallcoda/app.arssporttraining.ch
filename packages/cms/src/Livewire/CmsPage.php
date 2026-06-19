<?php

namespace Coda\Cms\Livewire;

use Coda\Cms\Registry;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

class CmsPage extends Component
{
    public string $pageName;

    public array $routeParameters = [];

    public function mount(): void
    {
        $route = request()->route();

        $this->pageName = (string) $route?->getName();
        $this->routeParameters = collect($route?->parameters() ?? [])
            ->map(fn (mixed $value): mixed => $value instanceof Model ? $value->getRouteKey() : $value)
            ->all();
    }

    public static function buildTitle(string $heading): string
    {
        $siteTitle = config('cms.site_title') ?? config('cms.name') ?? config('app.name', __('CMS'));
        $format = config('cms.title_format', ':page_name');

        return str_replace(
            [':site_title', ':page_name'],
            [$siteTitle, $heading],
            $format,
        );
    }

    public static function layout(): string
    {
        return config('cms.default_layout', 'cms::components.layouts.admin');
    }

    public function render()
    {
        $page = app(Registry::class)->page($this->pageName);
        $tabs = app(Registry::class)->tabsForRoute($this->pageName);

        $title = $page->title ?: static::buildTitle($page->heading);
        $layout = $page->layout ?: config('cms.default_layout', 'cms::components.layouts.admin');

        return view('cms::page', [
            'page' => $page,
            'tabs' => $tabs,
            'routeParameters' => $this->routeParameters,
        ])
            ->layout($layout)
            ->title($title);
    }
}
