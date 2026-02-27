<?php

namespace App\Livewire\Test\Athlete;

use App\Data\Exercise\ExerciseConfig;
use App\Data\Training\Config\EffectiveExerciseConfig;
use App\Data\Training\Config\Schedule\ScheduleWeek;
use App\Form\Fields\Training\Program\Color;
use App\Models\Exercise\Exercise;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class AthleteScheduleGridList extends Component
{
    public TrainingPlan $trainingPlan;

    public ?int $selectedAthleteId = null;

    public ?array $exerciseDetail = null;

    public int $slotExerciseIndex = 0;

    /** @var array<int, array{exerciseId: int, programId: int, name: string}> */
    public array $slotExerciseList = [];

    protected bool $autoOpenFirstExercise = true;

    public function mount(TrainingPlan $trainingPlan): void
    {
        $this->trainingPlan = $trainingPlan;
        $this->selectedAthleteId = session('athlete.selectedId');

        if ($this->autoOpenFirstExercise && $this->selectedAthleteId !== null) {
            $this->openFirstExercise();
        }
    }

    protected function openFirstExercise(): void
    {
        $firstWeek = $this->schedule->first();

        if (! $firstWeek) {
            return;
        }

        $resolvedSlots = $this->getResolvedSlots($firstWeek);

        for ($dayIndex = 0; $dayIndex < 7; $dayIndex++) {
            for ($slotKey = 0; $slotKey <= 1; $slotKey++) {
                $slotPrograms = $resolvedSlots[$dayIndex][$slotKey]['programs'] ?? [];

                if (empty($slotPrograms)) {
                    continue;
                }

                $exercises = $this->getProgramExercises($slotPrograms[0]);

                if (! empty($exercises)) {
                    $this->openExercise($exercises[0]['id'], $slotPrograms[0], $firstWeek->id, $dayIndex, $slotKey);

                    return;
                }
            }
        }
    }

    #[On('athlete-selected')]
    public function onAthleteSelected(?int $athleteId): void
    {
        $this->selectedAthleteId = $athleteId;
        $this->exerciseDetail = null;
        $this->slotExerciseList = [];
        $this->slotExerciseIndex = 0;
        unset($this->schedule);
        unset($this->programs);
        unset($this->previousExercise);
        unset($this->nextExercise);
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

    public function openExercise(int $exerciseId, int $programId, string $weekId, int $dayIndex, int $slotKey): void
    {
        $week = $this->schedule->firstWhere('id', $weekId);

        if (! $week) {
            return;
        }

        $resolvedSlots = $this->getResolvedSlots($week);
        $slotPrograms = $resolvedSlots[$dayIndex][$slotKey]['programs'] ?? [];

        $this->slotExerciseList = [];

        foreach ($slotPrograms as $slotProgramId) {
            foreach ($this->getProgramExercises($slotProgramId) as $exercise) {
                $this->slotExerciseList[] = [
                    'exerciseId' => $exercise['id'],
                    'programId' => $slotProgramId,
                    'name' => $exercise['name'],
                ];
            }
        }

        $index = collect($this->slotExerciseList)->search(
            fn (array $item) => $item['exerciseId'] === $exerciseId && $item['programId'] === $programId
        );

        if ($index === false) {
            return;
        }

        $this->loadExerciseDetail($index);

        Flux::modal('exercise-detail')->show();
    }

    public function navigateExercise(int $direction): void
    {
        $newIndex = $this->slotExerciseIndex + $direction;

        if ($newIndex < 0) {
            return;
        }

        if ($newIndex >= count($this->slotExerciseList)) {
            Flux::modal('exercise-detail')->close();

            return;
        }

        $this->loadExerciseDetail($newIndex);
    }

    /** @return array{name: string, color: string}|null */
    #[Computed]
    public function previousExercise(): ?array
    {
        $prevIndex = $this->slotExerciseIndex - 1;

        if ($prevIndex < 0 || empty($this->slotExerciseList)) {
            return null;
        }

        $entry = $this->slotExerciseList[$prevIndex];

        return [
            'name' => $entry['name'],
            'color' => $this->getProgramColor($entry['programId']),
        ];
    }

    /** @return array{name: string, color: string}|null */
    #[Computed]
    public function nextExercise(): ?array
    {
        $nextIndex = $this->slotExerciseIndex + 1;

        if ($nextIndex >= count($this->slotExerciseList) || empty($this->slotExerciseList)) {
            return null;
        }

        $entry = $this->slotExerciseList[$nextIndex];

        return [
            'name' => $entry['name'],
            'color' => $this->getProgramColor($entry['programId']),
        ];
    }

    protected function loadExerciseDetail(int $index): void
    {
        $this->slotExerciseIndex = $index;
        $entry = $this->slotExerciseList[$index];

        $exercise = Exercise::with(['category', 'equipment', 'modifiers'])->find($entry['exerciseId']);

        if (! $exercise) {
            return;
        }

        $config = $this->trainingPlan->config;
        $planOverrides = $config->defaultExerciseOverrides($exercise->id);
        $userOverrides = $this->selectedAthleteId !== null
            ? $config->userExerciseOverrides($exercise->id, $this->selectedAthleteId)
            : null;

        $resolved = EffectiveExerciseConfig::resolve($exercise->config, $planOverrides, $userOverrides);
        $resolvedConfig = ExerciseConfig::from($resolved);

        $badges = [];
        $setsBadges = $resolvedConfig->sets->badges();
        $badges = array_merge($badges, $setsBadges);

        foreach ($resolvedConfig->settings as $settingKey) {
            if ($resolvedConfig->{$settingKey} !== null) {
                $badges = array_merge($badges, $resolvedConfig->{$settingKey}->badges());
            }
        }

        $this->exerciseDetail = [
            'name' => $exercise->name,
            'category' => $exercise->category?->name,
            'equipment' => $exercise->equipment->pluck('name')->all(),
            'modifiers' => $exercise->modifiers->pluck('name')->all(),
            'videoUrl' => $exercise->video_url,
            'instructions' => $exercise->instructions,
            'badges' => $badges,
            'programName' => $this->getProgramName($entry['programId']),
            'programColor' => $this->getProgramColor($entry['programId']),
        ];

        unset($this->previousExercise);
        unset($this->nextExercise);
    }

    public function render()
    {
        return view('livewire.test.athlete.athlete-schedule-grid-list');
    }
}
