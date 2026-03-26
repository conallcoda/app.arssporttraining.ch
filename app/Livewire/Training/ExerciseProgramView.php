<?php

namespace App\Livewire\Training;

use App\Models\Exercise\ExerciseProgram;
use Coda\Cms\Livewire\CmsPage;
use Livewire\Component;

class ExerciseProgramView extends Component
{
    public ExerciseProgram $exerciseProgram;

    public function mount(ExerciseProgram $exerciseProgram): void
    {
        $this->exerciseProgram = $exerciseProgram;
    }

    public function updateName(string $name): void
    {
        $this->exerciseProgram->name = $name;
        $this->exerciseProgram->save();
    }

    public function render()
    {
        return view('livewire.training.exercise-program-view')
            ->layout(CmsPage::layout())
            ->title(CmsPage::buildTitle(__('Program')));
    }
}
