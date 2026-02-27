<?php

namespace App\Livewire\Training;

use App\Data\Training\TrainingProgramData;
use App\Livewire\Training\View\ScheduleHandler;
use App\Models\ExercisePlan;
use App\Models\TrainingPlanProgram;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.database')]
#[Title('ARS - Athlete Training // Exercise Plan')]
class ExercisePlanView extends Component
{
    #[Url]
    public string $tab = 'schedule';

    public ExercisePlan $exercisePlan;

    public Collection $programs;

    public Collection $users;

    public function mount(ExercisePlan $exercisePlan): void
    {
        $this->exercisePlan = $exercisePlan;
        $this->loadPrograms();
        $this->users = new Collection;
    }

    protected function loadPrograms(): void
    {
        $this->programs = TrainingPlanProgram::query()
            ->where('plannable_type', ExercisePlan::class)
            ->where('plannable_id', $this->exercisePlan->id)
            ->with([
                'exercises' => fn ($q) => $q->orderByPivot('sort'),
                'programCategory',
            ])
            ->orderBy('sort')
            ->get();
    }

    public function getDataKey(?string $domain = null): string
    {
        $parts = [
            'programs' => md5(json_encode(
                $this->programs->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sort' => $p->sort,
                    'exercises' => $p->exercises->pluck('id')->all(),
                ])->all()
            )),
            'config' => md5(json_encode($this->exercisePlan->config->toArray())),
        ];

        if ($domain && isset($parts[$domain])) {
            return $parts[$domain];
        }

        return md5(json_encode($parts));
    }

    public function updatedTab(string $value): void
    {
        $this->dispatch('tab-changed', tab: $value);
    }

    #[On('child-changed')]
    public function handleChildChanged(string $domain): void
    {
        $this->exercisePlan->refresh();

        match ($domain) {
            'programs' => $this->loadPrograms(),
            default => $this->loadPrograms(),
        };
    }

    #[On('data-changed')]
    public function handleDataChanged(string $key, mixed $value): void
    {
        match ($key) {
            'schedule' => $this->saveScheduleEvent($value),
            'startDate' => $this->saveStartDate($value),
            'program' => $this->saveProgram($value),
            'removeProgram' => $this->removeProgram($value),
            'target' => $this->saveTarget($value),
            'resetDefaults' => $this->resetDefaults(),
            'resetAll' => $this->resetAll(),
            default => null,
        };
    }

    #[On('refresh-requested')]
    public function handleRefreshRequested(): void
    {
        $this->exercisePlan->refresh();
        $this->loadPrograms();
    }

    protected function saveScheduleEvent(array $value): void
    {
        $handler = new ScheduleHandler($this->exercisePlan);
        $handler->handle($value['type'], $value['data'] ?? []);

        $this->exercisePlan->refresh();
    }

    protected function saveStartDate(array $value): void
    {
        $config = $this->exercisePlan->config;
        $config->setDefaultScheduleStartDate($value['startDate']);

        $this->exercisePlan->config = $config;
        $this->exercisePlan->save();
        $this->exercisePlan->refresh();
    }

    protected function saveTarget(array $value): void
    {
        $config = $this->exercisePlan->config;

        $config->setDefaultTarget(
            $value['measuredReps'] ?? null,
            $value['measuredWeight'] ?? null,
            $value['targetGoal'] ?? 10,
        );

        $this->exercisePlan->config = $config;
        $this->exercisePlan->save();
        $this->exercisePlan->refresh();
    }

    protected function saveProgram(array $value): void
    {
        $programData = TrainingProgramData::from($value['data']);
        $programData->plannable_type = ExercisePlan::class;
        $programData->plannable_id = $this->exercisePlan->id;

        if (! empty($value['editingProgramId'])) {
            $programData->id = $value['editingProgramId'];
        }

        $programData->persist();

        if (! empty($value['assigningWeekId']) && $value['assigningDay'] !== null && $value['assigningSlot'] !== null) {
            $handler = new ScheduleHandler($this->exercisePlan);
            $handler->handle('assign-program', [
                'weekId' => $value['assigningWeekId'],
                'day' => $value['assigningDay'],
                'slot' => $value['assigningSlot'],
                'programId' => $programData->id,
            ]);
        }

        $this->exercisePlan->refresh();
        $this->loadPrograms();
    }

    protected function removeProgram(array $value): void
    {
        $programId = $value['programId'];

        $config = $this->exercisePlan->config;
        $config->removeProgramFromAllSchedules($programId);
        $this->exercisePlan->config = $config;
        $this->exercisePlan->save();
        $this->exercisePlan->refresh();

        TrainingPlanProgram::find($programId)?->delete();

        $this->loadPrograms();
    }

    protected function resetDefaults(): void
    {
        $config = $this->exercisePlan->config;
        $config->resetDefaults();
        $this->exercisePlan->config = $config;
        $this->exercisePlan->save();
        $this->exercisePlan->refresh();
    }

    protected function resetAll(): void
    {
        $config = $this->exercisePlan->config;
        $config->resetAll();
        $this->exercisePlan->config = $config;
        $this->exercisePlan->save();
        $this->exercisePlan->refresh();
        $this->loadPrograms();
    }

    public function updateName(string $name): void
    {
        $this->exercisePlan->name = $name;
        $this->exercisePlan->save();
    }

    public function getTrainingPlan(): ExercisePlan
    {
        return $this->exercisePlan;
    }

    public function render()
    {
        return view('livewire.training.training-plan-view', [
            'trainingPlan' => $this->exercisePlan,
            'isTemplate' => true,
        ]);
    }
}
