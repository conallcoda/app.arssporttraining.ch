<?php

namespace Coda\Cms\Livewire;

use Coda\Cms\Registry;
use Livewire\Component;

class CmsPage extends Component
{
    public string $pageName;

    public function mount(): void
    {
        $this->pageName = request()->route()->getName();
    }

    public static function buildTitle(string $heading): string
    {
        $siteTitle = config('cms.site_title') ?? config('cms.name') ?? config('app.name', __('CMS'));
        $format = config('cms.title_format', ':site_title // :page_name');

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
        ])
            ->layout($layout)
            ->title($title);
    }
}
