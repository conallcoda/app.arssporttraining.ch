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

    public function render()
    {
        $page = app(Registry::class)->page($this->pageName);
        $tabs = app(Registry::class)->tabsForRoute($this->pageName);

        return view('cms::page', [
            'page' => $page,
            'tabs' => $tabs,
        ])
            ->layout($page->layout)
            ->title($page->title);
    }
}
