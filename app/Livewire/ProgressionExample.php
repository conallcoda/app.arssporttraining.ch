<?php

namespace App\Livewire;

use App\Models\Training\ExercisePlan\ExerciseBlockManager;
use App\Models\Training\ExercisePlan\ExerciseData;
use App\Models\Training\ExercisePlan\AthleteData;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ProgressionExample extends Component
{
    public array $athletes = [];

    public AthleteData $athlete;

    public array $exercises;

    public ExerciseData $exercise;

    public ExerciseBlockManager $manager;

    public function mount(): void
    {
        $this->athletes[] = AthleteData::example();
        $this->athlete = $this->athletes[0];
        $this->exercises = [
            ExerciseData::back_squat(),
            ExerciseData::front_squat(),
        ];

        $this->exercise = $this->exercises[1];
        $this->manager = ExerciseBlockManager::example($this->athlete, $this->exercise);
    }


    public function render()
    {
        return view('livewire.progression-example');
    }
}
