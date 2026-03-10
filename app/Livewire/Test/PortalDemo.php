<?php

namespace App\Livewire\Test;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('ARS - Athlete Training // Portal Demo')]
class PortalDemo extends Component
{
    public function render()
    {
        return view('livewire.test.portal-demo');
    }
}
