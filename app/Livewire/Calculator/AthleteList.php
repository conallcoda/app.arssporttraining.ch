<?php

namespace App\Livewire\Calculator;

use App\Models\Training\ExercisePlan\AthleteData;
use App\Models\Training\ExercisePlan\AthleteTestData;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class AthleteList extends Component
{
    use WithPagination;

    public array $data = [
        'forename' => '',
        'surname' => '',
        'reps' => 1,
        'weight' => 50.0,
        'target_modifier' => 100.0,
    ];

    public function mount(): void
    {
        $this->emitAthletes();
    }

    #[Computed]
    public function athletes()
    {
        return User::query()
            ->where('type', UserTypeEnum::Athlete)
            ->paginate(10);
    }

    public function updateAthlete(int $athleteId, string $field, mixed $value): void
    {
        $user = User::findOrFail($athleteId);
        $athlete = AthleteData::fromUser($user);

        if (str_starts_with($field, 'test.')) {
            $parts = explode('.', $field);
            $testIndex = (int) $parts[1];
            $testField = $parts[2];

            if (isset($athlete->tests[$testIndex])) {
                $athlete->tests[$testIndex] = new AthleteTestData(
                    exerciseId: $athlete->tests[$testIndex]->exerciseId,
                    reps: $testField === 'reps' ? (int) $value : $athlete->tests[$testIndex]->reps,
                    weight: $testField === 'weight' ? (float) $value : $athlete->tests[$testIndex]->weight,
                );
            }
        } else {
            $athlete->{$field} = $value;
        }

        $athlete->persist();

        unset($this->athletes);
        $this->emitAthletes();
    }

    public function addAthlete(): void
    {
        $this->validate([
            'data.forename' => 'required|string|min:1',
            'data.surname' => 'required|string|min:1',
            'data.reps' => 'required|integer|min:1',
            'data.weight' => 'required|numeric|min:1',
            'data.target_modifier' => 'required|numeric|min:0',
        ]);

        $athlete = new AthleteData(
            id: null,
            forename: $this->data['forename'],
            surname: $this->data['surname'],
            tests: [
                AthleteTestData::back_squat($this->data['reps'], $this->data['weight']),
            ],
            target_modifier: $this->data['target_modifier'],
        );

        $athlete->persist();

        $this->resetAddForm();
        unset($this->athletes);
        $this->emitAthletes();

        Flux::modal('add-athlete')->close();
    }

    public function removeAthlete(int $athleteId): void
    {
        User::where('id', $athleteId)->delete();

        unset($this->athletes);
        $this->emitAthletes();
    }

    protected function emitAthletes(): void
    {
        $allAthletes = User::query()
            ->where('type', UserTypeEnum::Athlete)
            ->get()
            ->map(fn(User $user) => AthleteData::fromUser($user)->toArray())
            ->all();

        $this->dispatch('athletes-updated', athletes: $allAthletes);
    }

    protected function resetAddForm(): void
    {
        $this->data = [
            'forename' => '',
            'surname' => '',
            'reps' => 1,
            'weight' => 50.0,
            'target_modifier' => 100.0,
        ];
    }

    public function render()
    {
        return view('livewire.calculator.athlete-list');
    }
}
