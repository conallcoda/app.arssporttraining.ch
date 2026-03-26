<?php

namespace App\Livewire\Database;

use App\Models\Users\User;
use Coda\Cms\Livewire\CmsPage;
use Livewire\Component;

class AthleteMetricIndex extends Component
{
    public User $athlete;

    public function mount(int $athleteId): void
    {
        $this->athlete = User::findOrFail($athleteId);
    }

    public function render()
    {
        return view('livewire.database.athlete-metric-index')
            ->layout(CmsPage::layout())
            ->title(CmsPage::buildTitle(__('Metrics')));
    }
}
