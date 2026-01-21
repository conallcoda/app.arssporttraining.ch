<?php

namespace App\Livewire\Calculator;

use App\Livewire\Concerns\ManagesDatabaseList;
use App\Models\Training\ExercisePlan\AthleteData;
use App\Models\Training\ExercisePlan\AthleteTestData;
use Livewire\Component;

class AthleteDatabase extends Component
{
    use ManagesDatabaseList;

    public array $athletes = [];

    public string $newName = '';

    public int $newReps = 1;

    public float $newWeight = 50.0;

    public function mount(): void
    {

        /*$this->athletes = [
            AthleteData::example(1, 'John Doe', 8, 45),
            AthleteData::example(2, 'Max Mustermann', 8, 65),
            AthleteData::example(3, 'Jan Modaal', 6, 65),
            AthleteData::example(4, 'Hans Muster', 8, 90),
        ];*/

        $weights = [
            52,
            55,
            57,
            60,
            102,
            105,
            107.5,
            115,
            122.5,
        ];

        $i = 0;
        foreach ($weights as $weight) {
            $this->athletes[] = AthleteData::example(
                id: $i + 1,
                name: 'Athlete '.$i + 1,
                reps: 1,
                weight: $weight * 1,
            );
            $i++;
        }

        $this->emitAthletes();
    }

    public function updateAthleteName(int $athleteId, string $value): void
    {
        foreach ($this->athletes as $index => $athlete) {
            if ($athlete->id === $athleteId) {
                $this->athletes[$index] = new AthleteData(
                    id: $athlete->id,
                    name: $value,
                    tests: $athlete->tests,
                );
                break;
            }
        }

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

    public function addAthlete(): void
    {
        $this->validate([
            'newName' => 'required|string|min:1',
            'newReps' => 'required|integer|min:1',
            'newWeight' => 'required|numeric|min:1',
        ]);

        $this->athletes[] = new AthleteData(
            id: $this->getNextId(),
            name: $this->newName,
            tests: [
                AthleteTestData::back_squat($this->newReps, $this->newWeight),
            ],
        );

        $this->resetAddForm();
        $this->showAddForm = false;
        $this->emitAthletes();
    }

    public function removeAthlete(int $athleteId): void
    {
        $this->athletes = array_values(
            array_filter($this->athletes, fn ($ath) => $ath->id !== $athleteId)
        );
        $this->emitAthletes();
    }

    public function moveAthleteUp(int $athleteId): void
    {
        foreach ($this->athletes as $index => $athlete) {
            if ($athlete->id === $athleteId && $index > 0) {
                $temp = $this->athletes[$index];
                $this->athletes[$index] = $this->athletes[$index - 1];
                $this->athletes[$index - 1] = $temp;

                $this->emitAthletes();
                break;
            }
        }
    }

    public function moveAthleteDown(int $athleteId): void
    {
        foreach ($this->athletes as $index => $athlete) {
            if ($athlete->id === $athleteId && $index < count($this->athletes) - 1) {
                $temp = $this->athletes[$index];
                $this->athletes[$index] = $this->athletes[$index + 1];
                $this->athletes[$index + 1] = $temp;

                $this->emitAthletes();
                break;
            }
        }
    }

    protected function emitAthletes(): void
    {
        $this->dispatch('athletes-updated', athletes: array_map(
            fn ($ath) => $ath->toArray(),
            $this->athletes
        ));
    }

    protected function getItems(): array
    {
        return $this->athletes;
    }

    protected function resetAddForm(): void
    {
        $this->newName = '';
        $this->newReps = 1;
        $this->newWeight = 50.0;
    }

    public function getListKey(): string
    {
        return md5(json_encode(array_map(fn ($ath) => $ath->id, $this->athletes)));
    }

    public function render()
    {
        return view('livewire.calculator.athlete-database');
    }
}
