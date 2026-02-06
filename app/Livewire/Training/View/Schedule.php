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

    public ?int $creatingForSlot = null;

    public ?int $editingProgramId = null;

    public ?string $linkingWeekId = null;

    public ?string $linkToWeekId = null;

    public ?string $removingWeekId = null;

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
        $userOverrides = $this->trainingPlan->config->get("users.{$userId}.schedule.weeks", []);

        if (empty($userOverrides)) {
            return false;
        }

        foreach ($userOverrides as $weekOverride) {
            if (! empty($weekOverride['slots']) || array_key_exists('linkedToWeekId', $weekOverride)) {
                return true;
            }
        }

        return false;
    }

    public function countUserScheduleChanges(int $userId): int
    {
        $userOverrides = $this->trainingPlan->config->get("users.{$userId}.schedule.weeks", []);

        if (empty($userOverrides)) {
            return 0;
        }

        $count = 0;

        foreach ($userOverrides as $weekOverride) {
            if (array_key_exists('linkedToWeekId', $weekOverride)) {
                $count++;
            }

            $count += count($weekOverride['slots'] ?? []);
        }

        return $count;
    }

    protected function getResolvedSlotsFromWeeks(array $week, array $allWeeks): array
    {
        if ($week['linkedToWeekId'] === null) {
            return $this->sparseToDense($week['slots'] ?? []);
        }

        $sourceWeek = collect($allWeeks)->firstWhere('id', $week['linkedToWeekId']);

        return $sourceWeek ? $this->getResolvedSlotsFromWeeks($sourceWeek, $allWeeks) : $this->sparseToDense($week['slots'] ?? []);
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
        return [];
    }

    protected function findSlot(array $slots, int $day, int $slot): ?array
    {
        foreach ($slots as $s) {
            if ($s['day'] === $day && $s['slot'] === $slot) {
                return $s;
            }
        }

        return null;
    }

    protected function setSlot(array &$slots, int $day, int $slot, ?int $programId, ?array $meta = null): void
    {
        $this->removeSlot($slots, $day, $slot);

        if ($programId !== null) {
            $slots[] = ['day' => $day, 'slot' => $slot, 'programId' => $programId];
        } elseif ($meta !== null) {
            $slotData = ['day' => $day, 'slot' => $slot, 'programId' => null];
            if (isset($meta['moved'])) {
                $slotData['moved'] = $meta['moved'];
            }
            if (isset($meta['deleted']) && $meta['deleted']) {
                $slotData['deleted'] = true;
            }
            $slots[] = $slotData;
        }
    }

    protected function removeSlot(array &$slots, int $day, int $slot): void
    {
        $slots = array_values(array_filter($slots, fn ($s) => ! ($s['day'] === $day && $s['slot'] === $slot)));
    }

    protected function sparseToDense(array $sparseSlots): array
    {
        $dense = [];
        for ($day = 0; $day < 7; $day++) {
            $dense[$day] = [
                0 => ['programId' => null],
                1 => ['programId' => null],
            ];
        }

        foreach ($sparseSlots as $slot) {
            $day = $slot['day'];
            $slotIndex = $slot['slot'];
            $dense[$day][$slotIndex] = [
                'programId' => $slot['programId'],
            ];
        }

        return $dense;
    }

    protected function resolveScheduleForUser(): array
    {
        $defaultWeeks = $this->trainingPlan->config->get('schedule.weeks', []);

        if ($this->user === null) {
            return $defaultWeeks;
        }

        $userOverrides = $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks", []);

        if (empty($userOverrides)) {
            return $defaultWeeks;
        }

        return collect($defaultWeeks)->map(function ($week) use ($userOverrides) {
            $weekId = $week['id'];
            $override = $userOverrides[$weekId] ?? null;

            if (! $override) {
                return $week;
            }

            if (array_key_exists('linkedToWeekId', $override)) {
                $week['linkedToWeekId'] = $override['linkedToWeekId'];
            }

            if (! empty($override['slots'])) {
                $week['slots'] = $this->mergeSlotOverrides($week['slots'] ?? [], $override['slots']);
            }

            return $week;
        })->all();
    }

    protected function mergeSlotOverrides(array $baseSlots, array $overrideSlots): array
    {
        $result = $baseSlots;

        foreach ($overrideSlots as $override) {
            $day = $override['day'];
            $slot = $override['slot'];
            $programId = $override['programId'] ?? null;

            $result = array_filter($result, fn ($s) => ! ($s['day'] === $day && $s['slot'] === $slot));

            if ($programId !== null) {
                $result[] = $override;
            }
        }

        return array_values($result);
    }

    protected function userHasSlotOverride(string $weekId, int $day, int $slot): bool
    {
        if ($this->user === null) {
            return false;
        }

        $userOverrides = $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks.{$weekId}.slots", []);

        foreach ($userOverrides as $override) {
            if ($override['day'] === $day && $override['slot'] === $slot) {
                return true;
            }
        }

        return false;
    }

    #[Computed]
    public function defaultSchedule(): array
    {
        return $this->trainingPlan->config->get('schedule.weeks', []);
    }

    #[Computed]
    public function schedule(): array
    {
        return $this->resolveScheduleForUser();
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

    public function countProgramReferences(int $programId): int
    {
        $count = 0;
        foreach ($this->schedule as $week) {
            $sparseSlots = $this->getResolvedSlotsRaw($week);
            foreach ($sparseSlots as $slot) {
                if (($slot['programId'] ?? null) === $programId) {
                    $count++;
                }
            }
        }

        return $count;
    }

    protected function getResolvedSlotsRaw(array $week): array
    {
        if ($week['linkedToWeekId'] === null) {
            return $week['slots'] ?? [];
        }

        $sourceWeek = collect($this->schedule)->firstWhere('id', $week['linkedToWeekId']);

        return $sourceWeek ? $this->getResolvedSlotsRaw($sourceWeek) : ($week['slots'] ?? []);
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
            return $this->sparseToDense($week['slots'] ?? []);
        }

        $sourceWeek = collect($this->schedule)->firstWhere('id', $week['linkedToWeekId']);

        return $sourceWeek ? $this->getResolvedSlots($sourceWeek) : $this->sparseToDense($week['slots'] ?? []);
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
        if ($this->user !== null) {
            return;
        }

        $weeks = $this->defaultSchedule;
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
        if ($this->removingWeekId === null || $this->user !== null) {
            $this->closeRemoveModal();

            return;
        }

        $weekId = $this->removingWeekId;
        $weekIndex = $this->getWeekIndex($weekId);
        if ($weekIndex === 0) {
            $this->closeRemoveModal();

            return;
        }

        $weeks = collect($this->defaultSchedule)
            ->filter(fn ($week) => $week['id'] !== $weekId)
            ->map(function ($week) use ($weekId) {
                if ($week['linkedToWeekId'] === $weekId) {
                    $week['linkedToWeekId'] = null;
                    $resolvedSlots = $this->getResolvedSlotsRaw($week);
                    $week['slots'] = array_map(function ($slot) {
                        return [
                            'day' => $slot['day'],
                            'slot' => $slot['slot'],
                            'programId' => $slot['programId'],
                        ];
                    }, $resolvedSlots);
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
        if ($this->linkingWeekId === null || $this->user !== null) {
            $this->closeLinkModal();

            return;
        }

        $weekIndex = $this->getWeekIndex($this->linkingWeekId);
        if ($weekIndex === 0) {
            $this->closeLinkModal();

            return;
        }

        $weeks = $this->defaultSchedule;
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
        if ($this->user !== null) {
            $this->unlinkWeekForUser($weekId);

            return;
        }

        $weeks = $this->defaultSchedule;
        foreach ($weeks as &$week) {
            if ($week['id'] === $weekId && $week['linkedToWeekId'] !== null) {
                $resolvedSparseSlots = $this->getResolvedSlotsRaw($week);

                $newSlots = [];
                foreach ($resolvedSparseSlots as $slot) {
                    if (($slot['programId'] ?? null) !== null) {
                        $newSlots[] = [
                            'day' => $slot['day'],
                            'slot' => $slot['slot'],
                            'programId' => $slot['programId'],
                        ];
                    }
                }

                $week['slots'] = $newSlots;
                $week['linkedToWeekId'] = null;
                break;
            }
        }

        $this->saveWeeks($weeks);
    }

    public function unlinkWeekForUser(string $weekId): void
    {
        if ($this->user === null) {
            return;
        }

        $week = collect($this->schedule)->firstWhere('id', $weekId);
        if (! $week || $week['linkedToWeekId'] === null) {
            return;
        }

        $resolvedSparseSlots = $this->getResolvedSlotsRaw($week);

        $copiedSlots = [];
        foreach ($resolvedSparseSlots as $slot) {
            if (($slot['programId'] ?? null) !== null) {
                $copiedSlots[] = [
                    'day' => $slot['day'],
                    'slot' => $slot['slot'],
                    'programId' => $slot['programId'],
                ];
            }
        }

        $userWeeks = $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks", []);

        $userWeeks[$weekId] = [
            'linkedToWeekId' => null,
            'slots' => $copiedSlots,
        ];

        $this->trainingPlan->config->set("users.{$this->user}.schedule.weeks", $userWeeks);
        $this->trainingPlan->save();
        $this->trainingPlan->refresh();
        unset($this->schedule);
        unset($this->defaultSchedule);
        $this->notifyChanged('schedule');
    }

    public function relinkWeekForUser(string $weekId): void
    {
        if ($this->user === null) {
            return;
        }

        $userWeeks = $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks", []);

        if (isset($userWeeks[$weekId])) {
            unset($userWeeks[$weekId]['linkedToWeekId']);

            if (empty($userWeeks[$weekId]['slots']) && ! array_key_exists('linkedToWeekId', $userWeeks[$weekId])) {
                unset($userWeeks[$weekId]);
            }
        }

        $this->trainingPlan->config->set("users.{$this->user}.schedule.weeks", $userWeeks);
        $this->trainingPlan->save();
        $this->trainingPlan->refresh();
        unset($this->schedule);
        unset($this->defaultSchedule);
        $this->notifyChanged('schedule');
    }

    public function userHasUnlinkedWeek(string $weekId): bool
    {
        if ($this->user === null) {
            return false;
        }

        $userOverride = $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks.{$weekId}", []);

        return array_key_exists('linkedToWeekId', $userOverride) && $userOverride['linkedToWeekId'] === null;
    }

    public function getDefaultWeekLinkedTo(string $weekId): ?string
    {
        $defaultWeek = collect($this->defaultSchedule)->firstWhere('id', $weekId);

        return $defaultWeek['linkedToWeekId'] ?? null;
    }

    public function openAddProgramModal(string $weekId, int $day, int $slot): void
    {
        $week = collect($this->schedule)->firstWhere('id', $weekId);
        if ($week && $week['linkedToWeekId'] !== null) {
            return;
        }

        $this->resetProgramForm();
        $this->creatingForWeekId = $weekId;
        $this->creatingForDay = $day;
        $this->creatingForSlot = $slot;

        Flux::modal('add-program')->show();
    }

    public function editProgram(int $programId, string $weekId, int $day, int $slot): void
    {
        $week = collect($this->schedule)->firstWhere('id', $weekId);
        if ($week && $week['linkedToWeekId'] !== null) {
            return;
        }

        $program = $this->programs->firstWhere('id', $programId);
        if (! $program) {
            return;
        }

        $this->creatingForWeekId = $weekId;
        $this->creatingForDay = $day;
        $this->creatingForSlot = $slot;
        $this->editingProgramId = $programId;

        $programData = TrainingProgramData::fromTrainingPlanProgram($program);
        $this->data = $programData->toArray();
        $this->ensureRelationshipItemsHaveKeys();

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
        $this->validate($this->buildValidationRulesFromFieldsets());

        $programData = TrainingProgramData::from($this->data);
        $programData->training_plan_id = $this->trainingPlan->id;

        if ($this->editingProgramId) {
            $programData->id = $this->editingProgramId;
        }

        $programData->persist();

        if ($this->creatingForWeekId !== null && $this->creatingForDay !== null && $this->creatingForSlot !== null) {
            $this->saveSlotChange($this->creatingForWeekId, $this->creatingForDay, $this->creatingForSlot, $programData->id);
        }

        $this->resetProgramForm();
        Flux::modal('add-program')->close();
        $this->notifyChanged('programs');
    }

    protected function saveSlotChange(string $weekId, int $day, int $slot, ?int $programId, ?array $meta = null): void
    {
        if ($this->user === null) {
            $weeks = $this->trainingPlan->config->get('schedule.weeks', []);
            foreach ($weeks as &$week) {
                if ($week['id'] === $weekId) {
                    $this->setSlot($week['slots'], $day, $slot, $programId);
                    break;
                }
            }
            $this->trainingPlan->config->set('schedule.weeks', $weeks);
        } else {
            $userWeeks = $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks", []);

            if (! isset($userWeeks[$weekId])) {
                $userWeeks[$weekId] = ['slots' => []];
            }

            $defaultSlot = $this->getDefaultSlotForWeek($weekId, $day, $slot);
            $defaultProgramId = $defaultSlot['programId'] ?? null;

            if ($programId === $defaultProgramId && $meta === null) {
                $this->removeSlot($userWeeks[$weekId]['slots'], $day, $slot);
            } else {
                $this->setSlot($userWeeks[$weekId]['slots'], $day, $slot, $programId, $meta);
            }

            if (empty($userWeeks[$weekId]['slots']) && ! array_key_exists('linkedToWeekId', $userWeeks[$weekId])) {
                unset($userWeeks[$weekId]);
            }

            $this->trainingPlan->config->set("users.{$this->user}.schedule.weeks", $userWeeks);
        }

        $this->trainingPlan->save();
        $this->trainingPlan->refresh();
        unset($this->schedule);
        unset($this->defaultSchedule);
        $this->notifyChanged('schedule');
    }

    protected function getDefaultSlotForWeek(string $weekId, int $day, int $slot): ?array
    {
        $defaultWeeks = $this->trainingPlan->config->get('schedule.weeks', []);
        $week = collect($defaultWeeks)->firstWhere('id', $weekId);

        if (! $week) {
            return null;
        }

        if ($week['linkedToWeekId'] !== null) {
            return $this->getDefaultSlotForWeek($week['linkedToWeekId'], $day, $slot);
        }

        return $this->findSlot($week['slots'] ?? [], $day, $slot);
    }

    public function clearProgramFromCell(): void
    {
        if ($this->creatingForWeekId === null || $this->creatingForDay === null || $this->creatingForSlot === null) {
            return;
        }

        $meta = $this->user !== null ? ['deleted' => true] : null;
        $this->saveSlotChange($this->creatingForWeekId, $this->creatingForDay, $this->creatingForSlot, null, $meta);
        $this->resetProgramForm();
        Flux::modal('add-program')->close();
    }

    public function confirmDeleteProgram(): void
    {
        Flux::modal('delete-program')->show();
    }

    public function removeFromSchedule(): void
    {
        if ($this->creatingForWeekId === null || $this->creatingForDay === null || $this->creatingForSlot === null) {
            return;
        }

        $programId = $this->editingProgramId;

        $meta = $this->user !== null ? ['deleted' => true] : null;
        $this->saveSlotChange($this->creatingForWeekId, $this->creatingForDay, $this->creatingForSlot, null, $meta);

        if ($programId !== null) {
            if ($this->user === null) {
                $weeks = $this->trainingPlan->config->get('schedule.weeks', []);
                foreach ($weeks as &$week) {
                    if ($week['linkedToWeekId'] !== null) {
                        continue;
                    }
                    $week['slots'] = array_values(array_filter(
                        $week['slots'] ?? [],
                        fn ($s) => ($s['programId'] ?? null) !== $programId
                    ));
                }
                $this->trainingPlan->config->set('schedule.weeks', $weeks);
                $this->trainingPlan->save();
            }

            $program = $this->programs->firstWhere('id', $programId);
            if ($program) {
                $program->delete();
                $this->programs = $this->programs->reject(fn ($p) => $p->id === $programId);
            }
        }

        $this->trainingPlan->refresh();
        unset($this->schedule);
        unset($this->defaultSchedule);

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
    public function moveProgram(string $weekId, int $fromDay, int $fromSlot, int $toDay, int $toSlot): void
    {
        $week = collect($this->schedule)->firstWhere('id', $weekId);
        if (! $week || $week['linkedToWeekId'] !== null) {
            return;
        }

        $fromSlotData = $this->findSlot($week['slots'] ?? [], $fromDay, $fromSlot);
        $programId = $fromSlotData['programId'] ?? null;

        $meta = $this->user !== null ? ['moved' => [$toDay, $toSlot]] : null;
        $this->saveSlotChange($weekId, $fromDay, $fromSlot, null, $meta);
        $this->saveSlotChange($weekId, $toDay, $toSlot, $programId);
    }

    #[On('program-swap')]
    public function swapPrograms(
        string $week1Id,
        int $day1,
        int $slot1,
        string $week2Id,
        int $day2,
        int $slot2
    ): void {
        $week1 = collect($this->schedule)->firstWhere('id', $week1Id);
        $week2 = collect($this->schedule)->firstWhere('id', $week2Id);

        if (! $week1 || ! $week2) {
            return;
        }

        if ($week1['linkedToWeekId'] !== null || $week2['linkedToWeekId'] !== null) {
            return;
        }

        $slot1Data = $this->findSlot($week1['slots'] ?? [], $day1, $slot1);
        $slot2Data = $this->findSlot($week2['slots'] ?? [], $day2, $slot2);

        $slot2ProgramId = $slot2Data['programId'] ?? null;
        $slot1ProgramId = $slot1Data['programId'] ?? null;

        $meta1 = ($this->user !== null && $slot2ProgramId === null && $slot1ProgramId !== null) ? ['moved' => [$day2, $slot2]] : null;
        $meta2 = ($this->user !== null && $slot1ProgramId === null && $slot2ProgramId !== null) ? ['moved' => [$day1, $slot1]] : null;

        $this->saveSlotChange($week1Id, $day1, $slot1, $slot2ProgramId, $meta1);
        $this->saveSlotChange($week2Id, $day2, $slot2, $slot1ProgramId, $meta2);
    }

    protected function saveWeeks(array $weeks): void
    {
        if ($this->user !== null) {
            return;
        }

        $this->trainingPlan->config->set('schedule.weeks', $weeks);
        $this->trainingPlan->save();
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
