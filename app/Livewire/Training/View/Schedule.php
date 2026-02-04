<?php

namespace App\Livewire\Training\View;

use App\Form\Fields\Training\Program\Color;
use App\Livewire\Concerns\InteractsWithParentView;
use App\Models\TrainingPlan;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Schedule extends Component
{
    use InteractsWithParentView;

    public TrainingPlan $trainingPlan;

    public Collection $programs;

    public ?string $assigningWeekId = null;

    public ?int $assigningDay = null;

    public ?string $assigningSlot = null;

    public ?int $assigningProgramId = null;

    public ?string $linkingWeekId = null;

    public ?string $linkToWeekId = null;

    public ?string $removingWeekId = null;

    public function mount(TrainingPlan $trainingPlan, Collection $programs): void
    {
        $this->trainingPlan = $trainingPlan;
        $this->programs = $programs;

        if (empty($this->schedule)) {
            $this->initializeSchedule();
            $this->trainingPlan->refresh();
            unset($this->schedule);
        }
    }

    protected function initializeSchedule(): void
    {
        $week1Id = (string) Str::uuid();

        $weeks = [
            [
                'id' => $week1Id,
                'linkedToWeekId' => null,
                'slots' => $this->createEmptySlots(),
            ],
        ];

        for ($i = 2; $i <= 5; $i++) {
            $weeks[] = [
                'id' => (string) Str::uuid(),
                'linkedToWeekId' => $week1Id,
                'slots' => [],
            ];
        }

        $this->trainingPlan->config->set('schedule.weeks', $weeks);
        $this->trainingPlan->save();
    }

    protected function createEmptySlots(): array
    {
        $slots = [];
        for ($day = 0; $day < 7; $day++) {
            $slots[] = [
                'am' => ['programId' => null],
                'pm' => ['programId' => null],
            ];
        }

        return $slots;
    }

    #[Computed]
    public function schedule(): array
    {
        return $this->trainingPlan->config->get('schedule.weeks', []);
    }

    #[Computed]
    public function programOptions(): array
    {
        return $this->programs->mapWithKeys(fn ($program) => [
            $program->id => $program->name,
        ])->all();
    }

    #[Computed]
    public function availableWeeksForLinking(): array
    {
        return collect($this->schedule)
            ->filter(fn ($week, $index) => $week['linkedToWeekId'] === null && $index !== $this->getWeekIndex($this->linkingWeekId))
            ->map(fn ($week, $index) => [
                'id' => $week['id'],
                'label' => 'Week '.($index + 1),
            ])
            ->values()
            ->all();
    }

    public function getWeekIndex(?string $weekId): ?int
    {
        if ($weekId === null) {
            return null;
        }

        foreach ($this->schedule as $index => $week) {
            if ($week['id'] === $weekId) {
                return $index;
            }
        }

        return null;
    }

    public function getResolvedSlots(array $week): array
    {
        if ($week['linkedToWeekId'] === null) {
            return $week['slots'];
        }

        $sourceWeek = collect($this->schedule)->firstWhere('id', $week['linkedToWeekId']);

        return $sourceWeek ? $this->getResolvedSlots($sourceWeek) : $week['slots'];
    }

    public function getProgramColor(?int $programId): string
    {
        if ($programId === null) {
            return Color::DEFAULT_COLOR;
        }

        $program = $this->programs->firstWhere('id', $programId);

        return $program?->config->get('color', Color::DEFAULT_COLOR) ?? Color::DEFAULT_COLOR;
    }

    public function getColorValue(?string $colorName, int $shade = 500): string
    {
        if (! $colorName) {
            return '#3b82f6';
        }

        $palettes = [
            'blue' => [50 => '#eff6ff', 100 => '#dbeafe', 200 => '#bfdbfe', 300 => '#93c5fd', 400 => '#60a5fa', 500 => '#3b82f6', 600 => '#2563eb', 700 => '#1d4ed8', 800 => '#1e40af', 900 => '#1e3a8a'],
            'green' => [50 => '#f0fdf4', 100 => '#dcfce7', 200 => '#bbf7d0', 300 => '#86efac', 400 => '#4ade80', 500 => '#22c55e', 600 => '#16a34a', 700 => '#15803d', 800 => '#166534', 900 => '#14532d'],
            'emerald' => [50 => '#ecfdf5', 100 => '#d1fae5', 200 => '#a7f3d0', 300 => '#6ee7b7', 400 => '#34d399', 500 => '#10b981', 600 => '#059669', 700 => '#047857', 800 => '#065f46', 900 => '#064e3b'],
            'teal' => [50 => '#f0fdfa', 100 => '#ccfbf1', 200 => '#99f6e4', 300 => '#5eead4', 400 => '#2dd4bf', 500 => '#14b8a6', 600 => '#0d9488', 700 => '#0f766e', 800 => '#115e59', 900 => '#134e4a'],
            'cyan' => [50 => '#ecfeff', 100 => '#cffafe', 200 => '#a5f3fc', 300 => '#67e8f9', 400 => '#22d3ee', 500 => '#06b6d4', 600 => '#0891b2', 700 => '#0e7490', 800 => '#155e75', 900 => '#164e63'],
            'sky' => [50 => '#f0f9ff', 100 => '#e0f2fe', 200 => '#bae6fd', 300 => '#7dd3fc', 400 => '#38bdf8', 500 => '#0ea5e9', 600 => '#0284c7', 700 => '#0369a1', 800 => '#075985', 900 => '#0c4a6e'],
            'indigo' => [50 => '#eef2ff', 100 => '#e0e7ff', 200 => '#c7d2fe', 300 => '#a5b4fc', 400 => '#818cf8', 500 => '#6366f1', 600 => '#4f46e5', 700 => '#4338ca', 800 => '#3730a3', 900 => '#312e81'],
            'violet' => [50 => '#f5f3ff', 100 => '#ede9fe', 200 => '#ddd6fe', 300 => '#c4b5fd', 400 => '#a78bfa', 500 => '#8b5cf6', 600 => '#7c3aed', 700 => '#6d28d9', 800 => '#5b21b6', 900 => '#4c1d95'],
            'purple' => [50 => '#faf5ff', 100 => '#f3e8ff', 200 => '#e9d5ff', 300 => '#d8b4fe', 400 => '#c084fc', 500 => '#a855f7', 600 => '#9333ea', 700 => '#7e22ce', 800 => '#6b21a8', 900 => '#581c87'],
            'pink' => [50 => '#fdf2f8', 100 => '#fce7f3', 200 => '#fbcfe8', 300 => '#f9a8d4', 400 => '#f472b6', 500 => '#ec4899', 600 => '#db2777', 700 => '#be185d', 800 => '#9d174d', 900 => '#831843'],
            'rose' => [50 => '#fff1f2', 100 => '#ffe4e6', 200 => '#fecdd3', 300 => '#fda4af', 400 => '#fb7185', 500 => '#f43f5e', 600 => '#e11d48', 700 => '#be123c', 800 => '#9f1239', 900 => '#881337'],
            'red' => [50 => '#fef2f2', 100 => '#fee2e2', 200 => '#fecaca', 300 => '#fca5a5', 400 => '#f87171', 500 => '#ef4444', 600 => '#dc2626', 700 => '#b91c1c', 800 => '#991b1b', 900 => '#7f1d1d'],
            'orange' => [50 => '#fff7ed', 100 => '#ffedd5', 200 => '#fed7aa', 300 => '#fdba74', 400 => '#fb923c', 500 => '#f97316', 600 => '#ea580c', 700 => '#c2410c', 800 => '#9a3412', 900 => '#7c2d12'],
            'amber' => [50 => '#fffbeb', 100 => '#fef3c7', 200 => '#fde68a', 300 => '#fcd34d', 400 => '#fbbf24', 500 => '#f59e0b', 600 => '#d97706', 700 => '#b45309', 800 => '#92400e', 900 => '#78350f'],
            'yellow' => [50 => '#fefce8', 100 => '#fef9c3', 200 => '#fef08a', 300 => '#fde047', 400 => '#facc15', 500 => '#eab308', 600 => '#ca8a04', 700 => '#a16207', 800 => '#854d0e', 900 => '#713f12'],
            'lime' => [50 => '#f7fee7', 100 => '#ecfccb', 200 => '#d9f99d', 300 => '#bef264', 400 => '#a3e635', 500 => '#84cc16', 600 => '#65a30d', 700 => '#4d7c0f', 800 => '#3f6212', 900 => '#365314'],
        ];

        $palette = $palettes[$colorName] ?? $palettes['blue'];

        return $palette[$shade] ?? $palette[500];
    }

    public function addWeek(): void
    {
        $weeks = $this->schedule;
        $week1Id = $weeks[0]['id'] ?? null;

        $weeks[] = [
            'id' => (string) Str::uuid(),
            'linkedToWeekId' => $week1Id,
            'slots' => [],
        ];

        $this->saveWeeks($weeks);
    }

    public function openRemoveModal(string $weekId): void
    {
        $weekIndex = $this->getWeekIndex($weekId);
        if ($weekIndex === 0) {
            return;
        }

        $this->removingWeekId = $weekId;
        Flux::modal('remove-week')->show();
    }

    public function closeRemoveModal(): void
    {
        $this->removingWeekId = null;
        Flux::modal('remove-week')->close();
    }

    public function confirmRemoveWeek(): void
    {
        if ($this->removingWeekId === null) {
            return;
        }

        $weekId = $this->removingWeekId;
        $weekIndex = $this->getWeekIndex($weekId);
        if ($weekIndex === 0) {
            $this->closeRemoveModal();

            return;
        }

        $weeks = collect($this->schedule)
            ->filter(fn ($week) => $week['id'] !== $weekId)
            ->map(function ($week) use ($weekId) {
                if ($week['linkedToWeekId'] === $weekId) {
                    $week['linkedToWeekId'] = null;
                    $week['slots'] = $this->getResolvedSlots($week);
                }

                return $week;
            })
            ->values()
            ->all();

        $this->saveWeeks($weeks);
        $this->closeRemoveModal();
    }

    public function openLinkModal(string $weekId): void
    {
        $weekIndex = $this->getWeekIndex($weekId);
        if ($weekIndex === 0) {
            return;
        }

        $this->linkingWeekId = $weekId;
        $week = collect($this->schedule)->firstWhere('id', $weekId);
        $this->linkToWeekId = $week['linkedToWeekId'];
        Flux::modal('link-week')->show();
    }

    public function closeLinkModal(): void
    {
        $this->linkingWeekId = null;
        $this->linkToWeekId = null;
        Flux::modal('link-week')->close();
    }

    public function linkWeek(): void
    {
        if ($this->linkingWeekId === null) {
            return;
        }

        $weekIndex = $this->getWeekIndex($this->linkingWeekId);
        if ($weekIndex === 0) {
            return;
        }

        $weeks = $this->schedule;
        foreach ($weeks as &$week) {
            if ($week['id'] === $this->linkingWeekId) {
                $week['linkedToWeekId'] = $this->linkToWeekId;
                if ($this->linkToWeekId !== null) {
                    $week['slots'] = [];
                }
                break;
            }
        }

        $this->saveWeeks($weeks);
        $this->closeLinkModal();
    }

    public function unlinkWeek(string $weekId): void
    {
        $weeks = $this->schedule;
        foreach ($weeks as &$week) {
            if ($week['id'] === $weekId && $week['linkedToWeekId'] !== null) {
                $week['slots'] = $this->getResolvedSlots($week);
                $week['linkedToWeekId'] = null;
                break;
            }
        }

        $this->saveWeeks($weeks);
    }

    public function startEditing(string $weekId, int $day, string $slot): void
    {
        $week = collect($this->schedule)->firstWhere('id', $weekId);
        if ($week && $week['linkedToWeekId'] !== null) {
            return;
        }

        $this->assigningWeekId = $weekId;
        $this->assigningDay = $day;
        $this->assigningSlot = $slot;

        $resolvedSlots = $this->getResolvedSlots($week);
        $this->assigningProgramId = $resolvedSlots[$day][$slot]['programId'] ?? null;
    }

    public function stopEditing(): void
    {
        $this->assigningWeekId = null;
        $this->assigningDay = null;
        $this->assigningSlot = null;
        $this->assigningProgramId = null;
    }

    public function isEditing(string $weekId, int $day, string $slot): bool
    {
        return $this->assigningWeekId === $weekId
            && $this->assigningDay === $day
            && $this->assigningSlot === $slot;
    }

    public function selectProgram(string|int|null $programId): void
    {
        if ($this->assigningWeekId === null || $this->assigningDay === null || $this->assigningSlot === null) {
            return;
        }

        $programId = $programId === '' || $programId === null ? null : (int) $programId;

        $weeks = $this->schedule;
        foreach ($weeks as &$week) {
            if ($week['id'] === $this->assigningWeekId) {
                $week['slots'][$this->assigningDay][$this->assigningSlot]['programId'] = $programId;
                break;
            }
        }

        $this->saveWeeks($weeks);
        $this->stopEditing();
    }

    #[On('program-move')]
    public function moveProgram(string $weekId, int $fromDay, string $fromSlot, int $toDay, string $toSlot): void
    {
        $weeks = $this->schedule;
        foreach ($weeks as &$week) {
            if ($week['id'] === $weekId) {
                if ($week['linkedToWeekId'] !== null) {
                    return;
                }

                $programId = $week['slots'][$fromDay][$fromSlot]['programId'] ?? null;
                $week['slots'][$fromDay][$fromSlot]['programId'] = null;
                $week['slots'][$toDay][$toSlot]['programId'] = $programId;
                break;
            }
        }

        $this->saveWeeks($weeks);
    }

    #[On('program-swap')]
    public function swapPrograms(
        string $week1Id,
        int $day1,
        string $slot1,
        string $week2Id,
        int $day2,
        string $slot2
    ): void {
        $weeks = $this->schedule;

        $program1Id = null;
        $program2Id = null;

        foreach ($weeks as $week) {
            if ($week['id'] === $week1Id) {
                if ($week['linkedToWeekId'] !== null) {
                    return;
                }
                $program1Id = $week['slots'][$day1][$slot1]['programId'] ?? null;
            }
            if ($week['id'] === $week2Id) {
                if ($week['linkedToWeekId'] !== null) {
                    return;
                }
                $program2Id = $week['slots'][$day2][$slot2]['programId'] ?? null;
            }
        }

        foreach ($weeks as &$week) {
            if ($week['id'] === $week1Id) {
                $week['slots'][$day1][$slot1]['programId'] = $program2Id;
            }
            if ($week['id'] === $week2Id) {
                $week['slots'][$day2][$slot2]['programId'] = $program1Id;
            }
        }

        $this->saveWeeks($weeks);
    }

    protected function saveWeeks(array $weeks): void
    {
        $this->trainingPlan->config->set('schedule.weeks', $weeks);
        $this->trainingPlan->save();
        $this->trainingPlan->refresh();
        unset($this->schedule);
        $this->notifyChanged('schedule');
    }

    public function render()
    {
        return view('livewire.training.view.schedule');
    }
}
