<?php

namespace App\Livewire;

use Coda\Cms\Livewire\CmsPage;
use Illuminate\View\View;
use Livewire\Component;

class Settings extends Component
{
    public function render(): View
    {
        return view('livewire.settings')
            ->layout(CmsPage::layout())
            ->title(CmsPage::buildTitle(__('Settings')));
    }
}
