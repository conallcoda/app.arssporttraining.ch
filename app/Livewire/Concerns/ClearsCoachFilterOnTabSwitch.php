<?php

namespace App\Livewire\Concerns;

trait ClearsCoachFilterOnTabSwitch
{
    public function updatedSelectedTab(): void
    {
        if ($this->selectedTab !== 'all') {
            unset($this->filters['coach']);
        }

        parent::updatedSelectedTab();
    }
}
