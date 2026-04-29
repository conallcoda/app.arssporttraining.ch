<?php

namespace App\Livewire\Test;

use App\Support\Readiness\ReadinessSurvey;
use Coda\Cms\Livewire\CmsPage;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ReadinessForm extends Component
{
    public array $form = [];

    public int $extremeOffset = -5;

    public function mount(): void
    {
        $this->form = ReadinessSurvey::defaultState();
    }

    #[Computed]
    public function readinessViewData(): array
    {
        return ReadinessSurvey::buildViewData($this->form, $this->extremeOffset);
    }

    public function render()
    {
        return view('livewire.test.readiness-form')
            ->layout(CmsPage::layout())
            ->title(CmsPage::buildTitle(__('Readiness')));
    }
}
