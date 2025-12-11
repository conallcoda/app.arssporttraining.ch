<?php

namespace App\Livewire\Calculator;

use App\Models\Training\ExercisePlan\AthleteData;
use App\Models\Training\ExercisePlan\AthleteTestData;
use Livewire\Component;

class AthleteDatabase extends Component
{
    public array $athletes = [];

    public function mount(): void
    {

        $this->athletes = [
            AthleteData::example(1, 'John Doe', 8, 45),
            AthleteData::example(2, 'Max Mustermann', 8, 65),
            AthleteData::example(3, 'Jan Modaal', 6, 65),
            AthleteData::example(4, 'Hans Muster', 8, 90),
        ];

        $this->emitAthletes();
    }

    public function updateAthleteTestReps(int $athleteId, int $testIndex, int $value): void
    {
        foreach ($this->athletes as $index => $athlete) {
            if ($athlete->id === $athleteId) {
                $tests = $athlete->tests;
                if (isset($tests[$testIndex])) {
                    $tests[$testIndex] = new AthleteTestData(
                        exerciseId: $tests[$testIndex]->exerciseId,
                        reps: $value,
                        weight: $tests[$testIndex]->weight,
                    );
                }
                $this->athletes[$index] = new AthleteData(
                    id: $athlete->id,
                    name: $athlete->name,
                    tests: $tests,
                );
                break;
            }
        }

        $this->emitAthletes();
    }

    public function updateAthleteTestWeight(int $athleteId, int $testIndex, float $value): void
    {
        foreach ($this->athletes as $index => $athlete) {
            if ($athlete->id === $athleteId) {
                $tests = $athlete->tests;
                if (isset($tests[$testIndex])) {
                    $tests[$testIndex] = new AthleteTestData(
                        exerciseId: $tests[$testIndex]->exerciseId,
                        reps: $tests[$testIndex]->reps,
                        weight: $value,
                    );
                }
                $this->athletes[$index] = new AthleteData(
                    id: $athlete->id,
                    name: $athlete->name,
                    tests: $tests,
                );
                break;
            }
        }

        $this->emitAthletes();
    }

    protected function emitAthletes(): void
    {
        $this->dispatch('athletes-updated', athletes: $this->athletes);
    }

    public function render()
    {
        return view('livewire.calculator.athlete-database');
    }
}
