<?php

namespace App\Livewire\Concerns;

trait ClearsOwnerFilterOnTabSwitch
{
    public function updatedSelectedTab(): void
    {
        if ($this->selectedTab !== 'all') {
            unset($this->filters['owner']);
        }

        parent::updatedSelectedTab();
    }
}
