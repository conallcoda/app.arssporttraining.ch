<?php

namespace App\Livewire\Training;

use App\Data\Training\TrainingProgramData;
use App\Livewire\Training\View\ScheduleHandler;
use App\Models\PlanTemplate;
use App\Models\TrainingPlanProgram;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.database')]
#[Title('ARS - Athlete Training // Plan Template')]
class PlanTemplateView extends Component
{
    #[Url]
    public string $tab = 'programs';

    public PlanTemplate $planTemplate;

    public Collection $programs;

    public Collection $users;

    public function mount(PlanTemplate $planTemplate): void
    {
        $this->planTemplate = $planTemplate;
        $this->loadPrograms();
        $this->users = new Collection;
    }

    protected function loadPrograms(): void
    {
        $this->programs = TrainingPlanProgram::query()
            ->where('plannable_type', PlanTemplate::class)
            ->where('plannable_id', $this->planTemplate->id)
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
            'config' => md5(json_encode($this->planTemplate->config->toArray())),
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
        $this->planTemplate->refresh();

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
        $this->planTemplate->refresh();
        $this->loadPrograms();
    }

    protected function saveScheduleEvent(array $value): void
    {
        $handler = new ScheduleHandler($this->planTemplate);
        $handler->handle($value['type'], $value['data'] ?? []);

        $this->planTemplate->refresh();
    }

    protected function saveStartDate(array $value): void
    {
        $config = $this->planTemplate->config;
        $config->setDefaultScheduleStartDate($value['startDate']);

        $this->planTemplate->config = $config;
        $this->planTemplate->save();
        $this->planTemplate->refresh();
    }

    protected function saveTarget(array $value): void
    {
        $config = $this->planTemplate->config;

        $config->setDefaultTarget(
            $value['measuredReps'] ?? null,
            $value['measuredWeight'] ?? null,
            $value['targetGoal'] ?? 10,
        );

        $this->planTemplate->config = $config;
        $this->planTemplate->save();
        $this->planTemplate->refresh();
    }

    protected function saveProgram(array $value): void
    {
        $programData = TrainingProgramData::from($value['data']);
        $programData->plannable_type = PlanTemplate::class;
        $programData->plannable_id = $this->planTemplate->id;

        if (! empty($value['editingProgramId'])) {
            $programData->id = $value['editingProgramId'];
        }

        $programData->persist();

        if (! empty($value['assigningWeekId']) && $value['assigningDay'] !== null && $value['assigningSlot'] !== null) {
            $handler = new ScheduleHandler($this->planTemplate);
            $handler->handle('assign-program', [
                'weekId' => $value['assigningWeekId'],
                'day' => $value['assigningDay'],
                'slot' => $value['assigningSlot'],
                'programId' => $programData->id,
            ]);
        }

        $this->planTemplate->refresh();
        $this->loadPrograms();
    }

    protected function removeProgram(array $value): void
    {
        $programId = $value['programId'];

        $config = $this->planTemplate->config;
        $config->removeProgramFromAllSchedules($programId);
        $this->planTemplate->config = $config;
        $this->planTemplate->save();
        $this->planTemplate->refresh();

        TrainingPlanProgram::find($programId)?->delete();

        $this->loadPrograms();
    }

    protected function resetDefaults(): void
    {
        $config = $this->planTemplate->config;
        $config->resetDefaults();
        $this->planTemplate->config = $config;
        $this->planTemplate->save();
        $this->planTemplate->refresh();
    }

    protected function resetAll(): void
    {
        $config = $this->planTemplate->config;
        $config->resetAll();
        $this->planTemplate->config = $config;
        $this->planTemplate->save();
        $this->planTemplate->refresh();
        $this->loadPrograms();
    }

    public function updateName(string $name): void
    {
        $this->planTemplate->name = $name;
        $this->planTemplate->save();
    }

    public function getTrainingPlan(): PlanTemplate
    {
        return $this->planTemplate;
    }

    public function render()
    {
        return view('livewire.training.training-plan-view', [
            'trainingPlan' => $this->planTemplate,
            'isTemplate' => true,
        ]);
    }
}
