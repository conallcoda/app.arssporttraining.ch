<?php

namespace Coda\Cms\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(): View
    {
        return view('cms::livewire.dashboard');
    }
}
