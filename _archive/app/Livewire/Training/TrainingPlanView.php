<?php

namespace App\Livewire\Training;

use App\Data\Training\TrainingProgramData;
use App\Livewire\Training\View\ScheduleHandler;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.database')]
#[Title('ARS - Athlete Training // Training Plan')]
class TrainingPlanView extends Component
{
    #[Url]
    public string $tab = 'athletes';

    public TrainingPlan $trainingPlan;

    public array $userIds = [];

    public array $userGroupIds = [];

    public Collection $programs;

    public Collection $users;

    public function mount(TrainingPlan $trainingPlan): void
    {
        $this->trainingPlan = $trainingPlan;
        $this->loadAllData();
    }

    protected function loadAllData(): void
    {
        $this->loadAthleteIds();
        $this->loadPrograms();
        $this->loadUsers();
    }

    protected function loadAthleteIds(): void
    {
        $this->trainingPlan->load(['users', 'userGroups']);
        $this->userIds = $this->trainingPlan->users->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->userGroupIds = $this->trainingPlan->userGroups->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    protected function loadPrograms(): void
    {
        $this->programs = TrainingPlanProgram::query()
            ->where('plannable_type', TrainingPlan::class)
            ->where('plannable_id', $this->trainingPlan->id)
            ->with([
                'exercises' => fn ($q) => $q->orderByPivot('sort'),
                'programCategory',
            ])
            ->orderBy('sort')
            ->get();
    }

    protected function loadUsers(): void
    {
        $this->users = $this->trainingPlan->allUsers()
            ->orderBy('forename')
            ->orderBy('surname')
            ->get();
    }

    protected function loadAthletes(): void
    {
        $this->loadAthleteIds();
        $this->loadUsers();
    }

    public function getDataKey(?string $domain = null): string
    {
        $parts = [
            'athletes' => md5(json_encode([
                $this->userIds,
                $this->userGroupIds,
            ])),
            'programs' => md5(json_encode(
                $this->programs->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sort' => $p->sort,
                    'exercises' => $p->exercises->pluck('id')->all(),
                ])->all()
            )),
            'users' => md5(json_encode($this->users->pluck('id')->all())),
            'config' => md5(json_encode($this->trainingPlan->config->toArray())),
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
        $this->trainingPlan->refresh();

        match ($domain) {
            'programs' => $this->loadPrograms(),
            default => $this->loadAllData(),
        };
    }

    #[On('data-changed')]
    public function handleDataChanged(string $key, mixed $value): void
    {
        match ($key) {
            'athletes' => $this->saveAthletes($value),
            'schedule' => $this->saveScheduleEvent($value),
            'startDate' => $this->saveStartDate($value),
            'program' => $this->saveProgram($value),
            'removeProgram' => $this->removeProgram($value),
            'target' => $this->saveTarget($value),
            'resetDefaults' => $this->resetDefaults(),
            'resetUserSettings' => $this->resetUserSettings(),
            'resetSingleUserSettings' => $this->resetSingleUserSettings($value),
            'resetAll' => $this->resetAll(),
            default => null,
        };

        $this->dispatch('parent-data-saved');
    }

    #[On('refresh-requested')]
    public function handleRefreshRequested(): void
    {
        $this->trainingPlan->refresh();
        $this->loadAllData();
    }

    protected function saveAthletes(array $value): void
    {
        $userIds = $value['userIds'] ?? [];
        $userGroupIds = $value['userGroupIds'] ?? [];

        $this->trainingPlan->users()->sync(
            $this->buildSyncDataWithSort(
                $userIds,
                $this->trainingPlan->users()->pluck('sort', 'user_id')->all()
            )
        );

        $this->trainingPlan->userGroups()->sync(
            $this->buildSyncDataWithSort(
                $userGroupIds,
                $this->trainingPlan->userGroups()->pluck('sort', 'user_group_id')->all()
            )
        );

        $this->loadAthletes();
    }

    protected function buildSyncDataWithSort(array $ids, array $existingSorts): array
    {
        $maxSort = empty($existingSorts) ? -1 : max($existingSorts);
        $result = [];

        foreach ($ids as $id) {
            if (isset($existingSorts[$id])) {
                $result[$id] = ['sort' => $existingSorts[$id]];
            } else {
                $maxSort++;
                $result[$id] = ['sort' => $maxSort];
            }
        }

        return $result;
    }

    protected function saveScheduleEvent(array $value): void
    {
        $handler = new ScheduleHandler($this->trainingPlan, $value['userId'] ?? null);
        $handler->handle($value['type'], $value['data'] ?? []);

        $this->trainingPlan->refresh();
    }

    protected function saveStartDate(array $value): void
    {
        $config = $this->trainingPlan->config;
        $userId = $value['userId'] ?? null;
        $startDate = $value['startDate'];

        if ($userId === null) {
            $config->setDefaultScheduleStartDate($startDate);
        } else {
            $config->setUserScheduleStartDate($userId, $startDate);
        }

        $this->trainingPlan->config = $config;
        $this->trainingPlan->save();
        $this->trainingPlan->refresh();
    }

    protected function saveTarget(array $value): void
    {
        $config = $this->trainingPlan->config;
        $userId = $value['userId'] ?? null;
        $measuredReps = $value['measuredReps'] ?? null;
        $measuredWeight = $value['measuredWeight'] ?? null;
        $targetGoal = $value['targetGoal'] ?? 10;

        if ($userId === null) {
            $config->setDefaultTarget($measuredReps, $measuredWeight, $targetGoal);
        } else {
            $config->setUserTarget($userId, $measuredReps, $measuredWeight, $targetGoal);
        }

        $this->trainingPlan->config = $config;
        $this->trainingPlan->save();
        $this->trainingPlan->refresh();
    }

    protected function saveProgram(array $value): void
    {
        $programData = TrainingProgramData::from($value['data']);
        $programData->plannable_type = TrainingPlan::class;
        $programData->plannable_id = $this->trainingPlan->id;

        if (! empty($value['editingProgramId'])) {
            $programData->id = $value['editingProgramId'];
        }

        $programData->persist();

        if (! empty($value['assigningWeekId']) && $value['assigningDay'] !== null && $value['assigningSlot'] !== null) {
            $handler = new ScheduleHandler($this->trainingPlan, $value['userId'] ?? null);
            $handler->handle('assign-program', [
                'weekId' => $value['assigningWeekId'],
                'day' => $value['assigningDay'],
                'slot' => $value['assigningSlot'],
                'programId' => $programData->id,
            ]);
        }

        $this->trainingPlan->refresh();
        $this->loadPrograms();
    }

    protected function removeProgram(array $value): void
    {
        $programId = $value['programId'];

        $config = $this->trainingPlan->config;
        $config->removeProgramFromAllSchedules($programId);
        $this->trainingPlan->config = $config;
        $this->trainingPlan->save();
        $this->trainingPlan->refresh();

        TrainingPlanProgram::find($programId)?->delete();

        $this->loadPrograms();
    }

    protected function resetDefaults(): void
    {
        $config = $this->trainingPlan->config;
        $config->resetDefaults();
        $this->trainingPlan->config = $config;
        $this->trainingPlan->save();
        $this->trainingPlan->refresh();
    }

    protected function resetUserSettings(): void
    {
        $config = $this->trainingPlan->config;
        $config->resetUserSettings();
        $this->trainingPlan->config = $config;
        $this->trainingPlan->save();
        $this->trainingPlan->refresh();
        $this->loadAllData();
    }

    protected function resetSingleUserSettings(array $value): void
    {
        $userId = $value['userId'] ?? null;

        if ($userId === null) {
            return;
        }

        $config = $this->trainingPlan->config;
        $config->resetSingleUserExerciseOverrides($userId);
        $this->trainingPlan->config = $config;
        $this->trainingPlan->save();
        $this->trainingPlan->refresh();
    }

    protected function resetAll(): void
    {
        $config = $this->trainingPlan->config;
        $config->resetAll();
        $this->trainingPlan->config = $config;
        $this->trainingPlan->save();
        $this->trainingPlan->refresh();
        $this->loadAllData();
    }

    public function updateName(string $name): void
    {
        $this->trainingPlan->name = $name;
        $this->trainingPlan->save();
    }

    public function render()
    {
        return view('livewire.training.training-plan-view', [
            'isTemplate' => false,
        ]);
    }
}
