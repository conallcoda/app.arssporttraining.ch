<?php

namespace App\Livewire\Test;

use Coda\Cms\Livewire\CmsPage;
use Livewire\Component;

class PortalDemo extends Component
{
    public function render()
    {
        return view('livewire.test.portal-demo')
            ->layout(CmsPage::layout())
            ->title(CmsPage::buildTitle(__('Portal Demo')));
    }
}
