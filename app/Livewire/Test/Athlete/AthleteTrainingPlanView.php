<?php

namespace App\Livewire\Test\Athlete;

use App\Data\Training\Config\EffectiveExerciseConfig;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.athlete')]
#[Title('Training Plan')]
class AthleteTrainingPlanView extends Component
{
    public TrainingPlan $trainingPlan;

    public ?int $selectedAthleteId = null;

    public ?int $selectedWeek = null;

    public ?int $selectedDay = null;

    public string $viewMode = 'calendar';

    public function mount(TrainingPlan $trainingPlan): void
    {
        $this->trainingPlan = $trainingPlan;
        $this->selectedAthleteId = session('athlete.selectedId');
    }

    #[On('athlete-selected')]
    public function onAthleteSelected(?int $athleteId): void
    {
        $this->selectedAthleteId = $athleteId;
        $this->selectedWeek = null;
        $this->selectedDay = null;
        unset($this->scheduleWeeks);
        unset($this->resolvedSlots);
        unset($this->slotContent);
    }

    public function updatingSelectedWeek(mixed $value): ?int
    {
        return $value === '' || $value === null ? null : (int) $value;
    }

    public function updatedSelectedWeek(): void
    {
        unset($this->resolvedSlots);
        unset($this->slotContent);
    }

    public function updatingSelectedDay(mixed $value): ?int
    {
        return $value === '' || $value === null ? null : (int) $value;
    }

    public function updatedSelectedDay(): void
    {
        unset($this->resolvedSlots);
        unset($this->slotContent);
    }

    #[Computed]
    public function scheduleWeeks(): array
    {
        $config = $this->trainingPlan->config;

        if ($this->selectedAthleteId !== null && ! $config->isUserScheduleLocked($this->selectedAthleteId)) {
            return $config->userScheduleWeeks($this->selectedAthleteId);
        }

        return $config->defaultScheduleWeeks();
    }

    #[Computed]
    public function weekOptions(): array
    {
        $options = ['' => 'All Weeks'];

        foreach ($this->scheduleWeeks as $index => $week) {
            $options[$index] = 'Week '.($index + 1);
        }

        return $options;
    }

    #[Computed]
    public function dayOptions(): array
    {
        return [
            '' => 'All Days',
            0 => 'Monday',
            1 => 'Tuesday',
            2 => 'Wednesday',
            3 => 'Thursday',
            4 => 'Friday',
            5 => 'Saturday',
            6 => 'Sunday',
        ];
    }

    public static array $dayNames = [
        0 => 'Monday',
        1 => 'Tuesday',
        2 => 'Wednesday',
        3 => 'Thursday',
        4 => 'Friday',
        5 => 'Saturday',
        6 => 'Sunday',
    ];

    /** @return array<int, array{weekIndex: int, day: int, slot: int, programs: int[]}> */
    #[Computed]
    public function resolvedSlots(): array
    {
        $weeks = $this->scheduleWeeks;

        if (empty($weeks)) {
            return [];
        }

        $weekIndices = $this->selectedWeek !== null
            ? [$this->selectedWeek]
            : array_keys($weeks);

        $result = [];

        foreach ($weekIndices as $weekIndex) {
            if (! isset($weeks[$weekIndex])) {
                continue;
            }

            $weekSlots = $this->getResolvedSlotsForWeek($weeks[$weekIndex], $weeks);

            foreach ($weekSlots as $slot) {
                $day = $slot['day'] ?? null;

                if ($this->selectedDay !== null && $day !== $this->selectedDay) {
                    continue;
                }

                $result[] = [
                    'weekIndex' => $weekIndex,
                    'day' => $day,
                    'slot' => $slot['slot'] ?? 0,
                    'programs' => $slot['programs'] ?? [],
                ];
            }
        }

        usort($result, function ($a, $b) {
            return $a['weekIndex'] <=> $b['weekIndex']
                ?: $a['day'] <=> $b['day']
                ?: $a['slot'] <=> $b['slot'];
        });

        return $result;
    }

    /** @return array<int, array{heading: string, programs: array}> */
    #[Computed]
    public function slotContent(): array
    {
        $slots = $this->resolvedSlots;

        if (empty($slots)) {
            return [];
        }

        $slotLabels = [0 => 'Morning', 1 => 'Afternoon'];
        $config = $this->trainingPlan->config;

        $allProgramIds = [];

        foreach ($slots as $slot) {
            foreach ($slot['programs'] as $programId) {
                $allProgramIds[] = $programId;
            }
        }

        if (empty($allProgramIds)) {
            return [];
        }

        $programs = TrainingPlanProgram::query()
            ->whereIn('id', array_unique($allProgramIds))
            ->with(['exercises' => fn ($q) => $q->orderByPivot('sort'), 'programCategory'])
            ->get()
            ->keyBy('id');

        $content = [];

        foreach ($slots as $slot) {
            $slotPrograms = [];

            foreach ($slot['programs'] as $programId) {
                $program = $programs->get($programId);

                if (! $program) {
                    continue;
                }

                $enabledExercises = $program->exercises->filter(function ($exercise) use ($config) {
                    $planOverrides = $config->defaultExerciseOverrides($exercise->id);

                    if ($this->selectedAthleteId !== null) {
                        $userOverrides = $config->userExerciseOverrides($exercise->id, $this->selectedAthleteId);

                        return ! EffectiveExerciseConfig::resolveDisabled($planOverrides, $userOverrides);
                    }

                    return ! ($planOverrides->disabled ?? false);
                });

                $slotPrograms[] = [
                    'id' => $program->id,
                    'name' => $program->name,
                    'exercises' => $enabledExercises->map(fn ($e) => [
                        'id' => $e->id,
                        'name' => $e->name,
                    ])->values()->all(),
                ];
            }

            if (! empty($slotPrograms)) {
                $dayName = self::$dayNames[$slot['day']] ?? 'Day '.$slot['day'];
                $timeLabel = $slotLabels[$slot['slot']] ?? 'Session '.$slot['slot'];

                $content[] = [
                    'heading' => 'Week '.($slot['weekIndex'] + 1).' - '.$dayName.' - '.$timeLabel,
                    'programs' => $slotPrograms,
                ];
            }
        }

        return $content;
    }

    protected function getResolvedSlotsForWeek(array $week, array $allWeeks): array
    {
        if (($week['linkedTo'] ?? null) === null) {
            return $week['slots'] ?? [];
        }

        $sourceWeek = collect($allWeeks)->firstWhere('id', $week['linkedTo']);

        return $sourceWeek ? $this->getResolvedSlotsForWeek($sourceWeek, $allWeeks) : ($week['slots'] ?? []);
    }

    public function render()
    {
        return view('livewire.test.athlete.athlete-training-plan-view');
    }
}
