<?php

namespace App\Livewire\Test\Athlete;

use App\Data\Training\Config\EffectiveExerciseConfig;
use App\Data\Training\Config\Schedule\ScheduleWeek;
use App\Form\Fields\Training\Program\Color;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class AthleteScheduleGrid extends Component
{
    public TrainingPlan $trainingPlan;

    public ?int $selectedAthleteId = null;

    public function mount(TrainingPlan $trainingPlan): void
    {
        $this->trainingPlan = $trainingPlan;
        $this->selectedAthleteId = session('athlete.selectedId');
    }

    #[On('athlete-selected')]
    public function onAthleteSelected(?int $athleteId): void
    {
        $this->selectedAthleteId = $athleteId;
        unset($this->schedule);
        unset($this->programs);
    }

    /** @return Collection<int, ScheduleWeek> */
    #[Computed]
    public function schedule(): Collection
    {
        $config = $this->trainingPlan->config;

        $weeks = ($this->selectedAthleteId !== null && ! $config->isUserScheduleLocked($this->selectedAthleteId))
            ? $config->userScheduleWeeks($this->selectedAthleteId)
            : $config->defaultScheduleWeeks();

        return collect($weeks)->map(fn (array $week, int $index) => ScheduleWeek::from([
            ...$week,
            'sort' => $week['sort'] ?? $index,
        ]));
    }

    #[Computed]
    public function programs(): \Illuminate\Database\Eloquent\Collection
    {
        return TrainingPlanProgram::query()
            ->where('plannable_type', TrainingPlan::class)
            ->where('plannable_id', $this->trainingPlan->id)
            ->with(['programCategory', 'exercises' => fn ($q) => $q->orderByPivot('sort')])
            ->get();
    }

    /** @return array<int, array{id: int, name: string}> */
    public function getProgramExercises(int $programId): array
    {
        $program = $this->programs->firstWhere('id', $programId);

        if (! $program) {
            return [];
        }

        $config = $this->trainingPlan->config;

        return $program->exercises->filter(function ($exercise) use ($config) {
            $planOverrides = $config->defaultExerciseOverrides($exercise->id);

            if ($this->selectedAthleteId !== null) {
                $userOverrides = $config->userExerciseOverrides($exercise->id, $this->selectedAthleteId);

                return ! EffectiveExerciseConfig::resolveDisabled($planOverrides, $userOverrides);
            }

            return ! ($planOverrides->disabled ?? false);
        })->map(fn ($e) => ['id' => $e->id, 'name' => $e->name])->values()->all();
    }

    public function getResolvedSlots(ScheduleWeek $week): array
    {
        if ($week->linkedTo === null) {
            return $week->grid();
        }

        $sourceWeek = $this->schedule->firstWhere('id', $week->linkedTo);

        return $sourceWeek ? $this->getResolvedSlots($sourceWeek) : $week->grid();
    }

    public function getProgramName(int $programId): string
    {
        return $this->programs->firstWhere('id', $programId)?->name ?? '?';
    }

    public function getProgramColor(int $programId): string
    {
        $program = $this->programs->firstWhere('id', $programId);

        return $program?->programCategory?->color ?? Color::DEFAULT_COLOR;
    }

    public function render()
    {
        return view('livewire.test.athlete.athlete-schedule-grid');
    }
}
