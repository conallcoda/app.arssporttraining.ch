<?php

namespace App\Livewire\Training\View;

use App\Data\Training\Config\Schedule\ScheduleWeek;
use App\Data\Training\Config\TrainingPlanConfig;
use App\Data\Training\TrainingProgramData;
use App\Form\Fields\Training\Program\Color;
use App\Models\TrainingPlan;
use App\Models\Users\User;
use App\Support\WeekOptions;
use Coda\Cms\Form\Field;
use Coda\Cms\Form\Fields\Relationship;
use Coda\Cms\Form\Form;
use Coda\Cms\Form\FormFieldset;
use Coda\Cms\Livewire\Concerns\InteractsWithParentView;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
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

    public ?string $startDate = null;

    public ?string $removingWeekId = null;

    public ?string $linkingWeekId = null;

    public ?string $linkToWeekId = null;

    public ?string $assigningWeekId = null;

    public ?int $assigningDay = null;

    public ?int $assigningSlot = null;

    public ?int $linkingProgramId = null;

    public ?int $editingProgramId = null;

    public array $data = [];

    public function mount(): void
    {
        $this->resetProgramForm();
        $this->loadStartDate();
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
        $this->loadStartDate();
        unset($this->schedule);
        unset($this->hasCustomSchedule);
    }

    #[Computed]
    public function config(): TrainingPlanConfig
    {
        return $this->trainingPlan->config;
    }

    #[Computed]
    public function hasCustomSchedule(): bool
    {
        if ($this->user === null) {
            return false;
        }

        return ! $this->config->isUserScheduleLocked($this->user);
    }

    /** @return BaseCollection<int, ScheduleWeek> */
    #[Computed]
    public function schedule(): BaseCollection
    {
        $weeks = ($this->user !== null && $this->hasCustomSchedule)
            ? $this->config->userScheduleWeeks($this->user)
            : $this->config->defaultScheduleWeeks();

        return collect($weeks)->map(fn (array $week, int $index) => ScheduleWeek::from([
            ...$week,
            'sort' => $week['sort'] ?? $index,
        ]));
    }

    #[Computed]
    public function weekOptions(): array
    {
        return WeekOptions::generate();
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
        return $this->schedule
            ->filter(fn (ScheduleWeek $week) => $week->linkedTo === null && $week->id !== $this->linkingWeekId)
            ->map(fn (ScheduleWeek $week, int $index) => [
                'id' => $week->id,
                'label' => 'Week '.($index + 1),
            ])
            ->values()
            ->all();
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
        return $this->formConfig->resolveFieldsets($this->data);
    }

    public function getResolvedSlots(ScheduleWeek $week): array
    {
        if ($week->linkedTo === null) {
            return $week->grid();
        }

        $sourceWeek = $this->schedule->firstWhere('id', $week->linkedTo);

        return $sourceWeek ? $this->getResolvedSlots($sourceWeek) : $week->grid();
    }

    public function getProgramColor(?int $programId): string
    {
        if ($programId === null) {
            return Color::DEFAULT_COLOR;
        }

        $program = $this->programs->firstWhere('id', $programId);

        return $program?->programCategory?->color ?? Color::DEFAULT_COLOR;
    }

    public function openRemoveModal(string $weekId): void
    {
        $this->removingWeekId = $weekId;
        Flux::modal('remove-week')->show();
    }

    public function confirmRemoveWeek(): void
    {
        if ($this->removingWeekId === null) {
            return;
        }

        $this->onScheduleEvent('remove-week', ['weekId' => $this->removingWeekId]);
        $this->removingWeekId = null;
        Flux::modal('remove-week')->close();
    }

    public function openLinkModal(string $weekId): void
    {
        $this->linkingWeekId = $weekId;
        $week = $this->schedule->firstWhere('id', $weekId);
        $this->linkToWeekId = $week?->linkedTo;
        unset($this->availableWeeksForLinking);
        Flux::modal('link-week')->show();
    }

    public function confirmLinkWeek(): void
    {
        if ($this->linkingWeekId === null) {
            return;
        }

        $linkToWeekId = $this->linkToWeekId ?: null;
        $this->onScheduleEvent('link-week', [
            'weekId' => $this->linkingWeekId,
            'linkToWeekId' => $linkToWeekId,
        ]);
        $this->linkingWeekId = null;
        $this->linkToWeekId = null;
        Flux::modal('link-week')->close();
    }

    public function editProgram(int $programId, string $weekId, int $day, int $slot): void
    {
        $week = $this->schedule->firstWhere('id', $weekId);
        if ($week && $week->linkedTo !== null) {
            return;
        }

        $program = $this->programs->firstWhere('id', $programId);
        if (! $program) {
            return;
        }

        $this->assigningWeekId = $weekId;
        $this->assigningDay = $day;
        $this->assigningSlot = $slot;
        $this->editingProgramId = $programId;

        $programData = TrainingProgramData::fromTrainingPlanProgram($program);
        $this->data = $programData->toArray();
        $this->ensureRelationshipItemsHaveKeys();

        Flux::modal('add-program')->show();
    }

    public function saveProgram(): void
    {
        $this->validate($this->buildValidationRulesFromFieldsets());

        $this->notifyDataChanged('program', [
            'data' => $this->data,
            'editingProgramId' => $this->editingProgramId,
            'assigningWeekId' => $this->assigningWeekId,
            'assigningDay' => $this->assigningDay,
            'assigningSlot' => $this->assigningSlot,
            'userId' => $this->user,
        ]);

        $this->resetProgramForm();
        Flux::modal('add-program')->close();
    }

    public function openLinkProgramModal(string $weekId, int $day, int $slot): void
    {
        $this->assigningWeekId = $weekId;
        $this->assigningDay = $day;
        $this->assigningSlot = $slot;
        $this->linkingProgramId = null;
        Flux::modal('link-program')->show();
    }

    public function confirmLinkProgram(): void
    {
        if ($this->assigningWeekId === null || $this->assigningDay === null || $this->assigningSlot === null || $this->linkingProgramId === null) {
            return;
        }

        $this->onScheduleEvent('assign-program', [
            'weekId' => $this->assigningWeekId,
            'day' => $this->assigningDay,
            'slot' => $this->assigningSlot,
            'programId' => $this->linkingProgramId,
        ]);
        $this->resetProgramForm();
        Flux::modal('link-program')->close();
    }

    public function confirmDeleteProgram(): void
    {
        Flux::modal('delete-program')->show();
    }

    public function removeFromSchedule(): void
    {
        if ($this->editingProgramId === null) {
            return;
        }

        $this->notifyDataChanged('removeProgram', [
            'programId' => $this->editingProgramId,
            'userId' => $this->user,
        ]);

        Flux::modal('delete-program')->close();
        Flux::modal('add-program')->close();
        $this->resetProgramForm();
    }

    public function resetProgramForm(): void
    {
        $this->editingProgramId = null;
        $this->assigningWeekId = null;
        $this->assigningDay = null;
        $this->assigningSlot = null;
        $this->linkingProgramId = null;
        $this->data = $this->buildDefaultsFromFieldsets();
    }

    public function updatedStartDate(): void
    {
        $this->notifyDataChanged('startDate', [
            'userId' => $this->user,
            'startDate' => $this->startDate,
        ]);
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

    #[On('schedule-event')]
    public function onScheduleEvent(string $type, array $data = []): void
    {
        $this->notifyDataChanged('schedule', [
            'type' => $type,
            'data' => $data,
            'userId' => $this->user,
        ]);
    }

    protected function loadStartDate(): void
    {
        $config = $this->trainingPlan->config;

        $startDate = $this->user === null
            ? $config->defaultScheduleStartDate()
            : $config->userScheduleStartDate($this->user);

        $this->startDate = ! empty($startDate) ? $startDate : WeekOptions::getCurrentWeekValue();
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

    public function render()
    {
        return view('livewire.training.view.schedule');
    }
}
