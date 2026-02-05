<?php

namespace App\Livewire\Training\View;

use App\Cms\Form\Field;
use App\Cms\Form\Fields\Relationship;
use App\Cms\Form\Form;
use App\Cms\Form\FormFieldset;
use App\Cms\Livewire\Concerns\InteractsWithParentView;
use App\Data\Training\TrainingProgramData;
use App\Form\Fields\Training\Program\Color;
use App\Models\TrainingPlan;
use App\Models\Users\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Schedule extends Component
{
    use InteractsWithParentView;

    public TrainingPlan $trainingPlan;

    public Collection $programs;

    public Collection $users;

    #[Url(except: null, as: 'user')]
    public int|string|null $user = null;

    public array $data = [];

    public ?string $creatingForWeekId = null;

    public ?int $creatingForDay = null;

    public ?string $creatingForSlot = null;

    public ?int $editingProgramId = null;

    public ?string $linkingWeekId = null;

    public ?string $linkToWeekId = null;

    public ?string $removingWeekId = null;

    public string $programMode = 'new';

    public ?int $selectedProgramId = null;

    public function updatedSelectedProgramId($value): void
    {
        logger()->info('updatedSelectedProgramId called', [
            'value' => $value,
            'type' => gettype($value),
        ]);
    }

    public function updatedProgramMode($value): void
    {
        if ($value === 'existing' && $this->programs->isNotEmpty()) {
            $this->selectedProgramId = $this->programs->first()->id;
        }
    }

    public function mount(TrainingPlan $trainingPlan, Collection $programs, Collection $users): void
    {
        $this->trainingPlan = $trainingPlan;
        $this->programs = $programs;
        $this->users = $users;
        $this->resetProgramForm();

        if (empty($this->defaultSchedule)) {
            $this->initializeSchedule();
            $this->trainingPlan->refresh();
            unset($this->defaultSchedule);
            unset($this->schedule);
        }
    }

    public function updatingUser(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        if ($this->user === null) {
            return null;
        }

        return $this->users->firstWhere('id', $this->user);
    }

    public function selectUser(?int $userId): void
    {
        $this->user = $userId;
        unset($this->schedule);
        $this->dispatch('schedule-user-changed', userId: $userId);
    }

    public function hasUserSchedule(int $userId): bool
    {
        $userSchedule = $this->trainingPlan->config->get("users.{$userId}.schedule.weeks");

        return ! empty($userSchedule);
    }

    public function countUserScheduleChanges(int $userId): int
    {
        if (! $this->hasUserSchedule($userId)) {
            return 0;
        }

        $defaultWeeks = $this->trainingPlan->config->get('schedule.weeks', []);
        $userWeeks = $this->trainingPlan->config->get("users.{$userId}.schedule.weeks", []);

        $removedPrograms = [];
        $addedPrograms = [];

        foreach ($userWeeks as $userWeek) {
            $userSlots = $this->getResolvedSlotsFromWeeks($userWeek, $userWeeks);
            $defaultWeek = collect($defaultWeeks)->firstWhere('id', $userWeek['id']);

            if (! $defaultWeek) {
                continue;
            }

            $defaultSlots = $this->getResolvedSlotsFromWeeks($defaultWeek, $defaultWeeks);

            foreach ($userSlots as $dayIndex => $daySlots) {
                foreach (['am', 'pm'] as $slotKey) {
                    $userProgramId = $daySlots[$slotKey]['programId'] ?? null;
                    $defaultProgramId = $defaultSlots[$dayIndex][$slotKey]['programId'] ?? null;

                    if ($userProgramId === $defaultProgramId) {
                        continue;
                    }

                    if ($defaultProgramId !== null) {
                        $removedPrograms[] = $defaultProgramId;
                    }

                    if ($userProgramId !== null) {
                        $addedPrograms[] = $userProgramId;
                    }
                }
            }
        }

        $changes = 0;
        $processedMoves = [];

        foreach ($removedPrograms as $programId) {
            if (in_array($programId, $addedPrograms) && ! in_array($programId, $processedMoves)) {
                $changes++;
                $processedMoves[] = $programId;
            } elseif (! in_array($programId, $addedPrograms)) {
                $changes++;
            }
        }

        foreach ($addedPrograms as $programId) {
            if (! in_array($programId, $removedPrograms)) {
                $changes++;
            }
        }

        return $changes;
    }

    protected function getResolvedSlotsFromWeeks(array $week, array $allWeeks): array
    {
        if ($week['linkedToWeekId'] === null) {
            return $week['slots'] ?? [];
        }

        $sourceWeek = collect($allWeeks)->firstWhere('id', $week['linkedToWeekId']);

        return $sourceWeek ? $this->getResolvedSlotsFromWeeks($sourceWeek, $allWeeks) : ($week['slots'] ?? []);
    }

    public function customizeSchedule(): void
    {
        if ($this->user === null) {
            return;
        }

        $defaultWeeks = $this->trainingPlan->config->get('schedule.weeks', []);
        $this->trainingPlan->config->set("users.{$this->user}.schedule.weeks", $defaultWeeks);
        $this->trainingPlan->save();
        $this->trainingPlan->refresh();
        unset($this->schedule);
        $this->notifyChanged('schedule');
    }

    public function resetToDefault(): void
    {
        if ($this->user === null) {
            return;
        }

        $this->trainingPlan->config->forget("users.{$this->user}.schedule");
        $this->trainingPlan->save();
        $this->trainingPlan->refresh();
        unset($this->schedule);
        $this->notifyChanged('schedule');
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
    public function defaultSchedule(): array
    {
        return $this->trainingPlan->config->get('schedule.weeks', []);
    }

    #[Computed]
    public function schedule(): array
    {
        if ($this->user !== null && $this->hasUserSchedule($this->user)) {
            return $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks", []);
        }

        return $this->defaultSchedule;
    }

    public function isViewingCustomSchedule(): bool
    {
        return $this->user !== null && $this->hasUserSchedule($this->user);
    }

    public function isViewingInheritedSchedule(): bool
    {
        return $this->user !== null && ! $this->hasUserSchedule($this->user);
    }

    #[Computed]
    public function programOptions(): array
    {
        return $this->programs->mapWithKeys(fn ($program) => [
            $program->id => $program->name,
        ])->all();
    }

    #[Computed]
    public function availableProgramsForLinking(): array
    {
        return $this->programs->map(fn ($program) => [
            'id' => $program->id,
            'name' => $program->name,
            'color' => $program->config->get('color', Color::DEFAULT_COLOR),
        ])->values()->all();
    }

    public function hasAvailablePrograms(): bool
    {
        return $this->programs->isNotEmpty();
    }

    public function isProgramLinkedInSlot(array $slot): bool
    {
        return ($slot['isLinked'] ?? false) === true;
    }

    public function countProgramReferences(int $programId): int
    {
        $count = 0;
        foreach ($this->schedule as $week) {
            $slots = $this->getResolvedSlots($week);
            foreach ($slots as $daySlots) {
                foreach (['am', 'pm'] as $slotKey) {
                    if (($daySlots[$slotKey]['programId'] ?? null) === $programId) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    public function countLinkedProgramReferences(int $programId): int
    {
        $count = 0;
        foreach ($this->schedule as $week) {
            if ($week['linkedToWeekId'] !== null) {
                continue;
            }
            $slots = $week['slots'] ?? [];
            foreach ($slots as $daySlots) {
                foreach (['am', 'pm'] as $slotKey) {
                    $slot = $daySlots[$slotKey] ?? [];
                    if (($slot['programId'] ?? null) === $programId && ($slot['isLinked'] ?? false) === true) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    #[Computed]
    public function formConfig(): Form
    {
        return TrainingProgramData::getForm();
    }

    #[Computed]
    public function fields(): array
    {
        return $this->formConfig->getFields();
    }

    #[Computed]
    public function fieldsets(): array
    {
        $form = $this->formConfig;
        $allFields = $form->getFields();

        if ($form->hasFieldsets()) {
            $fieldsMap = collect($allFields)->keyBy('name');

            return collect($form->getFieldsets())->map(function ($config, $name) use ($fieldsMap) {
                if (is_callable($config)) {
                    $config = $config($this->data);
                }

                if ($config === null) {
                    return null;
                }

                $resolvedFields = collect($config['fields'])
                    ->map(fn ($field) => is_string($field) ? $fieldsMap->get($field) : $field)
                    ->filter()
                    ->values()
                    ->all();

                return FormFieldset::make($name)
                    ->label($config['label'])
                    ->fields($resolvedFields)
                    ->prefix($config['prefix'] ?? null);
            })->filter()->values()->all();
        }

        return [
            FormFieldset::make('general')
                ->label('General')
                ->fields($allFields),
        ];
    }

    protected function getAllFields(): array
    {
        return collect($this->fieldsets)
            ->flatMap(fn (FormFieldset $fs) => $fs->fields)
            ->all();
    }

    protected function buildValidationRulesFromFieldsets(): array
    {
        $rules = [];

        foreach ($this->fieldsets as $fieldset) {
            $prefix = $fieldset->prefix ?? 'data';
            $fieldRules = Field::buildValidationRules($fieldset->fields, $prefix.'.');
            $rules = array_merge($rules, $fieldRules);
        }

        return $rules;
    }

    protected function buildDefaultsFromFieldsets(): array
    {
        $defaults = [];

        foreach ($this->fieldsets as $fieldset) {
            $fieldDefaults = Field::buildDefaults($fieldset->fields);

            if ($fieldset->prefix && $fieldset->prefix !== 'data') {
                $nestedKey = str_replace('data.', '', $fieldset->prefix);
                data_set($defaults, $nestedKey, $fieldDefaults);
            } else {
                $defaults = array_merge($defaults, $fieldDefaults);
            }
        }

        return $defaults;
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
                $resolvedSlots = $this->getResolvedSlots($week);
                foreach ($resolvedSlots as &$daySlots) {
                    foreach (['am', 'pm'] as $slotKey) {
                        if (isset($daySlots[$slotKey]['programId']) && $daySlots[$slotKey]['programId'] !== null) {
                            $daySlots[$slotKey]['isLinked'] = true;
                        }
                    }
                }
                $week['slots'] = $resolvedSlots;
                $week['linkedToWeekId'] = null;
                break;
            }
        }

        $this->saveWeeks($weeks);
    }

    public function openAddProgramModal(string $weekId, int $day, string $slot): void
    {
        $week = collect($this->schedule)->firstWhere('id', $weekId);
        if ($week && $week['linkedToWeekId'] !== null) {
            return;
        }

        $this->resetProgramForm();
        $this->creatingForWeekId = $weekId;
        $this->creatingForDay = $day;
        $this->creatingForSlot = $slot;
        $this->programMode = 'new';
        $this->selectedProgramId = null;

        Flux::modal('add-program')->show();
    }

    public function editProgram(int $programId, string $weekId, int $day, string $slot): void
    {
        $week = collect($this->schedule)->firstWhere('id', $weekId);
        if ($week && $week['linkedToWeekId'] !== null) {
            return;
        }

        $program = $this->programs->firstWhere('id', $programId);
        if (! $program) {
            return;
        }

        $slotData = $week['slots'][$day][$slot] ?? [];
        $isLinked = $slotData['isLinked'] ?? false;

        $this->creatingForWeekId = $weekId;
        $this->creatingForDay = $day;
        $this->creatingForSlot = $slot;
        $this->editingProgramId = $programId;

        if ($isLinked) {
            $this->programMode = 'existing';
            $this->selectedProgramId = $programId;
            $this->data = $this->buildDefaultsFromFieldsets();
        } else {
            $this->programMode = 'new';
            $this->selectedProgramId = null;
            $programData = TrainingProgramData::fromTrainingPlanProgram($program);
            $this->data = $programData->toArray();
            $this->ensureRelationshipItemsHaveKeys();
        }

        Flux::modal('add-program')->show();
    }

    protected function ensureRelationshipItemsHaveKeys(): void
    {
        $relationshipFields = collect($this->getAllFields())
            ->filter(fn (Field $field) => $field instanceof Relationship)
            ->pluck('name')
            ->all();

        foreach ($relationshipFields as $fieldName) {
            if (! isset($this->data[$fieldName]) || ! is_array($this->data[$fieldName])) {
                continue;
            }

            foreach ($this->data[$fieldName] as $index => $item) {
                if (! isset($item['_key'])) {
                    $this->data[$fieldName][$index]['_key'] = uniqid('item_', true);
                }
            }
        }
    }

    public function saveProgram(): void
    {
        logger()->info('saveProgram called', [
            'programMode' => $this->programMode,
            'selectedProgramId' => $this->selectedProgramId,
            'creatingForWeekId' => $this->creatingForWeekId,
            'creatingForDay' => $this->creatingForDay,
            'creatingForSlot' => $this->creatingForSlot,
        ]);

        if ($this->programMode === 'existing') {
            $this->saveProgramLink();

            return;
        }

        $this->validate($this->buildValidationRulesFromFieldsets());

        $programData = TrainingProgramData::from($this->data);
        $programData->training_plan_id = $this->trainingPlan->id;

        if ($this->editingProgramId && $this->programMode === 'new') {
            $programData->id = $this->editingProgramId;
        }

        $programData->persist();

        if ($this->creatingForWeekId !== null && $this->creatingForDay !== null && $this->creatingForSlot !== null) {
            $weeks = $this->schedule;
            foreach ($weeks as &$week) {
                if ($week['id'] === $this->creatingForWeekId) {
                    $week['slots'][$this->creatingForDay][$this->creatingForSlot] = [
                        'programId' => $programData->id,
                        'isLinked' => false,
                    ];
                    break;
                }
            }
            $this->saveWeeks($weeks);
        }

        $this->resetProgramForm();
        Flux::modal('add-program')->close();
        $this->notifyChanged('programs');
    }

    public function saveProgramLink(): void
    {
        logger()->info('saveProgramLink called', [
            'selectedProgramId' => $this->selectedProgramId,
            'selectedProgramId_type' => gettype($this->selectedProgramId),
            'creatingForWeekId' => $this->creatingForWeekId,
            'creatingForDay' => $this->creatingForDay,
            'creatingForSlot' => $this->creatingForSlot,
        ]);

        if ($this->selectedProgramId === null || $this->selectedProgramId === '') {
            logger()->warning('saveProgramLink early return: selectedProgramId is null or empty');

            return;
        }

        if ($this->creatingForWeekId === null || $this->creatingForDay === null || $this->creatingForSlot === null) {
            logger()->warning('saveProgramLink early return: slot info missing', [
                'creatingForWeekId' => $this->creatingForWeekId,
                'creatingForDay' => $this->creatingForDay,
                'creatingForSlot' => $this->creatingForSlot,
            ]);

            return;
        }

        $programId = (int) $this->selectedProgramId;

        $weeks = $this->schedule;
        $weekFound = false;

        foreach ($weeks as &$week) {
            if ($week['id'] === $this->creatingForWeekId) {
                $weekFound = true;
                logger()->info('saveProgramLink: Found week, updating slot', [
                    'weekId' => $week['id'],
                    'day' => $this->creatingForDay,
                    'slot' => $this->creatingForSlot,
                    'programId' => $programId,
                    'slotBefore' => $week['slots'][$this->creatingForDay][$this->creatingForSlot] ?? 'NOT SET',
                ]);

                $week['slots'][$this->creatingForDay][$this->creatingForSlot] = [
                    'programId' => $programId,
                    'isLinked' => true,
                ];
                break;
            }
        }

        if (! $weekFound) {
            logger()->warning('saveProgramLink: Week not found', [
                'lookingFor' => $this->creatingForWeekId,
                'availableWeekIds' => collect($weeks)->pluck('id')->all(),
            ]);
        }

        logger()->info('saveProgramLink: Calling saveWeeks');
        $this->saveWeeks($weeks);
        $this->resetProgramForm();
        Flux::modal('add-program')->close();
        logger()->info('saveProgramLink: Completed');
    }

    public function clearProgramFromCell(): void
    {
        if ($this->creatingForWeekId === null || $this->creatingForDay === null || $this->creatingForSlot === null) {
            return;
        }

        $weeks = $this->schedule;
        foreach ($weeks as &$week) {
            if ($week['id'] === $this->creatingForWeekId) {
                $week['slots'][$this->creatingForDay][$this->creatingForSlot] = [
                    'programId' => null,
                    'isLinked' => false,
                ];
                break;
            }
        }

        $this->saveWeeks($weeks);
        $this->resetProgramForm();
        Flux::modal('add-program')->close();
    }

    public function confirmDeleteProgram(): void
    {
        Flux::modal('delete-program')->show();
    }

    public function isEditingLinkedProgram(): bool
    {
        if ($this->creatingForWeekId === null || $this->creatingForDay === null || $this->creatingForSlot === null) {
            return false;
        }

        $week = collect($this->schedule)->firstWhere('id', $this->creatingForWeekId);
        if (! $week) {
            return false;
        }

        $slot = $week['slots'][$this->creatingForDay][$this->creatingForSlot] ?? [];

        return ($slot['isLinked'] ?? false) === true;
    }

    public function getEditingProgramLinkedCount(): int
    {
        if ($this->editingProgramId === null || $this->isEditingLinkedProgram()) {
            return 0;
        }

        return $this->countLinkedProgramReferences($this->editingProgramId);
    }

    public function removeFromSchedule(): void
    {
        if ($this->creatingForWeekId === null || $this->creatingForDay === null || $this->creatingForSlot === null) {
            return;
        }

        $isLinked = $this->isEditingLinkedProgram();
        $programId = $this->editingProgramId;

        $weeks = $this->schedule;
        foreach ($weeks as &$week) {
            if ($week['id'] === $this->creatingForWeekId) {
                $week['slots'][$this->creatingForDay][$this->creatingForSlot] = [
                    'programId' => null,
                    'isLinked' => false,
                ];
                break;
            }
        }

        if (! $isLinked && $programId !== null) {
            foreach ($weeks as &$week) {
                if ($week['linkedToWeekId'] !== null) {
                    continue;
                }
                foreach ($week['slots'] as $dayIndex => &$daySlots) {
                    foreach (['am', 'pm'] as $slotKey) {
                        $slot = $daySlots[$slotKey] ?? [];
                        if (($slot['programId'] ?? null) === $programId) {
                            $daySlots[$slotKey] = ['programId' => null, 'isLinked' => false];
                        }
                    }
                }
            }

            $program = $this->programs->firstWhere('id', $programId);
            if ($program) {
                $program->delete();
                $this->programs = $this->programs->reject(fn ($p) => $p->id === $programId);
            }
        }

        $this->saveWeeks($weeks);
        Flux::modal('delete-program')->close();
        Flux::modal('add-program')->close();
        $this->resetProgramForm();
        $this->notifyChanged('programs');
    }

    public function resetProgramForm(): void
    {
        $this->editingProgramId = null;
        $this->creatingForWeekId = null;
        $this->creatingForDay = null;
        $this->creatingForSlot = null;
        $this->programMode = 'new';
        $this->selectedProgramId = null;
        $this->data = $this->buildDefaultsFromFieldsets();
    }

    public function addRelationshipItem(string $fieldName): void
    {
        if (! isset($this->data[$fieldName])) {
            $this->data[$fieldName] = [];
        }

        $field = collect($this->getAllFields())->firstWhere('name', $fieldName);
        $newItem = [
            $field?->valueAttribute ?? 'id' => null,
            '_key' => uniqid('item_', true),
        ];

        if ($field?->sortable) {
            $newItem['sort'] = count($this->data[$fieldName]);
        }

        $this->data[$fieldName][] = $newItem;
    }

    public function removeRelationshipItem(string $fieldName, int $index): void
    {
        if (! isset($this->data[$fieldName][$index])) {
            return;
        }

        unset($this->data[$fieldName][$index]);
        $this->data[$fieldName] = array_values($this->data[$fieldName]);

        $field = collect($this->getAllFields())->firstWhere('name', $fieldName);
        if ($field?->sortable) {
            foreach ($this->data[$fieldName] as $i => $item) {
                $this->data[$fieldName][$i]['sort'] = $i;
            }
        }
    }

    public function moveRelationshipItem(string $fieldName, int $index, int $direction): void
    {
        if (! isset($this->data[$fieldName])) {
            return;
        }

        $newIndex = $index + $direction;
        if ($newIndex < 0 || $newIndex >= count($this->data[$fieldName])) {
            return;
        }

        $items = $this->data[$fieldName];
        [$items[$index], $items[$newIndex]] = [$items[$newIndex], $items[$index]];

        $field = collect($this->getAllFields())->firstWhere('name', $fieldName);
        if ($field?->sortable) {
            foreach ($items as $i => $item) {
                $items[$i]['sort'] = $i;
            }
        }

        $this->data[$fieldName] = $items;
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

                $fromSlotData = $week['slots'][$fromDay][$fromSlot] ?? ['programId' => null, 'isLinked' => false];
                $week['slots'][$fromDay][$fromSlot] = ['programId' => null, 'isLinked' => false];
                $week['slots'][$toDay][$toSlot] = $fromSlotData;
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

        $slot1Data = null;
        $slot2Data = null;

        foreach ($weeks as $week) {
            if ($week['id'] === $week1Id) {
                if ($week['linkedToWeekId'] !== null) {
                    return;
                }
                $slot1Data = $week['slots'][$day1][$slot1] ?? ['programId' => null, 'isLinked' => false];
            }
            if ($week['id'] === $week2Id) {
                if ($week['linkedToWeekId'] !== null) {
                    return;
                }
                $slot2Data = $week['slots'][$day2][$slot2] ?? ['programId' => null, 'isLinked' => false];
            }
        }

        foreach ($weeks as &$week) {
            if ($week['id'] === $week1Id) {
                $week['slots'][$day1][$slot1] = $slot2Data;
            }
            if ($week['id'] === $week2Id) {
                $week['slots'][$day2][$slot2] = $slot1Data;
            }
        }

        $this->saveWeeks($weeks);
    }

    protected function saveWeeks(array $weeks): void
    {
        $configPath = $this->user !== null
            ? "users.{$this->user}.schedule.weeks"
            : 'schedule.weeks';

        logger()->info('saveWeeks called', [
            'configPath' => $configPath,
            'user' => $this->user,
            'trainingPlanId' => $this->trainingPlan->id,
            'weeksCount' => count($weeks),
        ]);

        $this->trainingPlan->config->set($configPath, $weeks);

        $this->trainingPlan->save();

        logger()->info('saveWeeks: Model saved', [
            'trainingPlanId' => $this->trainingPlan->id,
            'configAfterSave' => $this->trainingPlan->config->get($configPath),
        ]);

        $this->trainingPlan->refresh();
        unset($this->schedule);
        unset($this->defaultSchedule);
        $this->notifyChanged('schedule');
    }

    public function render()
    {
        return view('livewire.training.view.schedule');
    }
}
