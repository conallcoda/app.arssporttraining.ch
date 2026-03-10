<?php

namespace App\Livewire\Test;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('ARS - Athlete Training // Exercise Creator')]
class ExerciseCreator extends Component
{
    public int $defaultWeeks = 5;

    public int $defaultSessionsPerWeek = 1;

    public bool $showPreview = true;

    public bool $showData = false;

    public bool $flyout = true;

    public string $maxWidth = 'max-w-[83.333%]';

    public function handleFormSubmitted(array $data): void
    {
        //
    }

    /** @return array<string, string> */
    public function getListeners(): array
    {
        return [
            'exercise-form.submitted' => 'handleFormSubmitted',
        ];
    }

    public function render()
    {
        return view('livewire.test.exercise-creator');
    }
}
