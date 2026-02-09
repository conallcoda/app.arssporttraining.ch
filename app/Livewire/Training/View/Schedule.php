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

    public ?int $linkingProgramId = null;

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

        $defaultWeekIds = collect($this->defaultSchedule)->pluck('id')->all();

        foreach ($userOverrides as $weekOverride) {
            $weekId = $weekOverride['id'] ?? null;

            if (! in_array($weekId, $defaultWeekIds)) {
                return true;
            }

            if (! empty($weekOverride['removed']) || ! empty($weekOverride['slots']) || array_key_exists('linkedTo', $weekOverride)) {
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

        $defaultWeekIds = collect($this->defaultSchedule)->pluck('id')->all();
        $count = 0;

        foreach ($userOverrides as $weekOverride) {
            $weekId = $weekOverride['id'] ?? null;

            if (! in_array($weekId, $defaultWeekIds)) {
                $count++;

                continue;
            }

            if (! empty($weekOverride['removed'])) {
                $count++;

                continue;
            }

            if (array_key_exists('linkedTo', $weekOverride)) {
                $count++;
            }

            $count += count($weekOverride['slots'] ?? []);
        }

        return $count;
    }

    protected function getResolvedSlotsFromWeeks(array $week, array $allWeeks): array
    {
        if ($week['linkedTo'] === null) {
            return $this->sparseToDense($week['slots'] ?? []);
        }

        $sourceWeek = collect($allWeeks)->firstWhere('id', $week['linkedTo']);

        return $sourceWeek ? $this->getResolvedSlotsFromWeeks($sourceWeek, $allWeeks) : $this->sparseToDense($week['slots'] ?? []);
    }

    protected function findUserWeekOverride(array $userWeeks, string $weekId): ?array
    {
        foreach ($userWeeks as $week) {
            if (($week['id'] ?? null) === $weekId) {
                return $week;
            }
        }

        return null;
    }

    protected function findUserWeekOverrideIndex(array $userWeeks, string $weekId): ?int
    {
        foreach ($userWeeks as $index => $week) {
            if (($week['id'] ?? null) === $weekId) {
                return $index;
            }
        }

        return null;
    }

    protected function setUserWeekOverride(array &$userWeeks, string $weekId, array $data): void
    {
        $data['id'] = $weekId;
        $index = $this->findUserWeekOverrideIndex($userWeeks, $weekId);

        if ($index !== null) {
            $userWeeks[$index] = $data;
        } else {
            $userWeeks[] = $data;
        }
    }

    protected function removeUserWeekOverride(array &$userWeeks, string $weekId): void
    {
        $userWeeks = array_values(array_filter($userWeeks, fn ($week) => ($week['id'] ?? null) !== $weekId));
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
        $week1Id = 'default_0';

        $weeks = [
            [
                'id' => $week1Id,
                'linkedTo' => null,
                'slots' => $this->createEmptySlots(),
                'sort' => 0,
            ],
        ];

        for ($i = 1; $i < 5; $i++) {
            $weeks[] = [
                'id' => "default_{$i}",
                'linkedTo' => $week1Id,
                'slots' => [],
                'sort' => $i,
            ];
        }

        $this->trainingPlan->config->set('default.schedule.weeks', $weeks);
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
        $defaultWeeks = $this->trainingPlan->config->get('default.schedule.weeks', []);

        if ($this->user === null) {
            return $defaultWeeks;
        }

        $userOverrides = $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks", []);

        if (empty($userOverrides)) {
            return $defaultWeeks;
        }

        $defaultWeekIds = collect($defaultWeeks)->pluck('id')->all();

        $weeks = collect($defaultWeeks)->map(function ($week, $index) use ($userOverrides) {
            $weekId = $week['id'];
            $override = $this->findUserWeekOverride($userOverrides, $weekId);

            if (! isset($week['sort'])) {
                $week['sort'] = $index;
            }

            if (! $override) {
                return $week;
            }

            if (! empty($override['removed'])) {
                return null;
            }

            if (array_key_exists('linkedTo', $override)) {
                $week['linkedTo'] = $override['linkedTo'];
            }

            if (! empty($override['slots'])) {
                $week['slots'] = $this->mergeSlotOverrides($week['slots'] ?? [], $override['slots']);
            }

            return $week;
        })->filter();

        $userAddedWeeks = collect($userOverrides)
            ->filter(fn ($override) => ! in_array($override['id'] ?? null, $defaultWeekIds));

        return $weeks->merge($userAddedWeeks)
            ->sortBy('sort')
            ->values()
            ->all();
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

        $userWeeks = $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks", []);
        $weekOverride = $this->findUserWeekOverride($userWeeks, $weekId);
        $slots = $weekOverride['slots'] ?? [];

        foreach ($slots as $override) {
            if ($override['day'] === $day && $override['slot'] === $slot) {
                return true;
            }
        }

        return false;
    }

    #[Computed]
    public function defaultSchedule(): array
    {
        return $this->trainingPlan->config->get('default.schedule.weeks', []);
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
        if ($week['linkedTo'] === null) {
            return $week['slots'] ?? [];
        }

        $sourceWeek = collect($this->schedule)->firstWhere('id', $week['linkedTo']);

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
        return $this->formConfig->resolveFieldsets($this->data);
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
            ->filter(fn ($week, $index) => $week['linkedTo'] === null && $index !== $this->getWeekIndex($this->linkingWeekId))
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
        if ($week['linkedTo'] === null) {
            return $this->sparseToDense($week['slots'] ?? []);
        }

        $sourceWeek = collect($this->schedule)->firstWhere('id', $week['linkedTo']);

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

    public function addWeek(): void
    {
        if ($this->user !== null) {
            $this->addWeekForUser();

            return;
        }

        $weeks = $this->defaultSchedule;
        $week1Id = $weeks[0]['id'] ?? null;
        $maxSort = collect($weeks)->max('sort') ?? count($weeks) - 1;

        $newSort = $maxSort + 1;

        $weeks[] = [
            'id' => "default_{$newSort}",
            'linkedTo' => $week1Id,
            'slots' => [],
            'sort' => $newSort,
        ];

        $this->saveWeeks($weeks);
    }

    protected function addWeekForUser(): void
    {
        $schedule = $this->schedule;
        $firstWeekId = $schedule[0]['id'] ?? null;
        $maxSort = collect($schedule)->max('sort') ?? count($schedule) - 1;

        $newSort = $maxSort + 1;
        $newWeekId = "user_{$newSort}";
        $userWeeks = $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks", []);

        $userWeeks[] = [
            'id' => $newWeekId,
            'linkedTo' => $firstWeekId,
            'slots' => [],
            'sort' => $newSort,
        ];

        $this->trainingPlan->config->set("users.{$this->user}.schedule.weeks", $userWeeks);
        $this->trainingPlan->save();
        $this->trainingPlan->refresh();
        unset($this->schedule);
        unset($this->defaultSchedule);
        $this->notifyChanged('schedule');
    }

    protected function isUserAddedWeek(string $weekId): bool
    {
        if ($this->user === null) {
            return false;
        }

        return ! collect($this->defaultSchedule)->contains('id', $weekId);
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
            $this->closeRemoveModal();

            return;
        }

        if ($this->user !== null) {
            $this->removeWeekForUser($this->removingWeekId);
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
                if ($week['linkedTo'] === $weekId) {
                    $week['linkedTo'] = null;
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
        $this->linkToWeekId = $week['linkedTo'];
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
                $week['linkedTo'] = $this->linkToWeekId;
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
            if ($week['id'] === $weekId && $week['linkedTo'] !== null) {
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
                $week['linkedTo'] = null;
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
        if (! $week || $week['linkedTo'] === null) {
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

        if ($this->isUserAddedWeek($weekId)) {
            $index = $this->findUserWeekOverrideIndex($userWeeks, $weekId);
            if ($index !== null) {
                $userWeeks[$index]['linkedTo'] = null;
                $userWeeks[$index]['slots'] = $copiedSlots;
            }
        } else {
            $this->setUserWeekOverride($userWeeks, $weekId, [
                'linkedTo' => null,
                'slots' => $copiedSlots,
            ]);
        }

        $this->trainingPlan->config->set("users.{$this->user}.schedule.weeks", array_values($userWeeks));
        $this->trainingPlan->save();
        $this->trainingPlan->refresh();
        unset($this->schedule);
        unset($this->defaultSchedule);
        $this->notifyChanged('schedule');
    }

    public function removeWeekForUser(string $weekId): void
    {
        if ($this->user === null) {
            return;
        }

        $weekIndex = $this->getWeekIndex($weekId);
        if ($weekIndex === 0) {
            return;
        }

        $userWeeks = $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks", []);

        if ($this->isUserAddedWeek($weekId)) {
            $this->removeUserWeekOverride($userWeeks, $weekId);
        } else {
            $existing = $this->findUserWeekOverride($userWeeks, $weekId) ?? [];
            $this->setUserWeekOverride($userWeeks, $weekId, array_merge($existing, [
                'removed' => true,
            ]));
        }

        $this->trainingPlan->config->set("users.{$this->user}.schedule.weeks", array_values($userWeeks));
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
        $index = $this->findUserWeekOverrideIndex($userWeeks, $weekId);

        if ($index !== null) {
            unset($userWeeks[$index]['linkedTo']);

            if (empty($userWeeks[$index]['slots']) && ! array_key_exists('linkedTo', $userWeeks[$index])) {
                $this->removeUserWeekOverride($userWeeks, $weekId);
            }
        }

        $this->trainingPlan->config->set("users.{$this->user}.schedule.weeks", array_values($userWeeks));
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

        $userWeeks = $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks", []);
        $userOverride = $this->findUserWeekOverride($userWeeks, $weekId);

        if (! $userOverride) {
            return false;
        }

        return array_key_exists('linkedTo', $userOverride) && $userOverride['linkedTo'] === null;
    }

    public function getDefaultWeekLinkedTo(string $weekId): ?string
    {
        $defaultWeek = collect($this->defaultSchedule)->firstWhere('id', $weekId);

        return $defaultWeek['linkedTo'] ?? null;
    }

    public function openAddProgramModal(string $weekId, int $day, int $slot): void
    {
        $week = collect($this->schedule)->firstWhere('id', $weekId);
        if ($week && $week['linkedTo'] !== null) {
            return;
        }

        $this->resetProgramForm();
        $this->creatingForWeekId = $weekId;
        $this->creatingForDay = $day;
        $this->creatingForSlot = $slot;

        Flux::modal('add-program')->show();
    }

    public function openLinkProgramModal(string $weekId, int $day, int $slot): void
    {
        $week = collect($this->schedule)->firstWhere('id', $weekId);
        if ($week && $week['linkedTo'] !== null) {
            return;
        }

        $this->creatingForWeekId = $weekId;
        $this->creatingForDay = $day;
        $this->creatingForSlot = $slot;
        $this->linkingProgramId = null;

        Flux::modal('link-program')->show();
    }

    public function linkProgramToSlot(): void
    {
        if ($this->creatingForWeekId === null || $this->creatingForDay === null || $this->creatingForSlot === null || $this->linkingProgramId === null) {
            return;
        }

        $program = $this->programs->firstWhere('id', $this->linkingProgramId);
        if (! $program) {
            return;
        }

        $this->saveSlotChange($this->creatingForWeekId, $this->creatingForDay, $this->creatingForSlot, $this->linkingProgramId);

        $this->linkingProgramId = null;
        $this->creatingForWeekId = null;
        $this->creatingForDay = null;
        $this->creatingForSlot = null;
        Flux::modal('link-program')->close();
    }

    public function editProgram(int $programId, string $weekId, int $day, int $slot): void
    {
        $week = collect($this->schedule)->firstWhere('id', $weekId);
        if ($week && $week['linkedTo'] !== null) {
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
            $weeks = $this->trainingPlan->config->get('default.schedule.weeks', []);
            foreach ($weeks as &$week) {
                if ($week['id'] === $weekId) {
                    $this->setSlot($week['slots'], $day, $slot, $programId);
                    break;
                }
            }
            $this->trainingPlan->config->set('default.schedule.weeks', $weeks);
        } elseif ($this->isUserAddedWeek($weekId)) {
            $userWeeks = $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks", []);
            $index = $this->findUserWeekOverrideIndex($userWeeks, $weekId);
            if ($index !== null) {
                $this->setSlot($userWeeks[$index]['slots'], $day, $slot, $programId);
                $this->trainingPlan->config->set("users.{$this->user}.schedule.weeks", $userWeeks);
            }
        } else {
            $userWeeks = $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks", []);
            $index = $this->findUserWeekOverrideIndex($userWeeks, $weekId);

            if ($index === null) {
                $userWeeks[] = ['id' => $weekId, 'slots' => []];
                $index = count($userWeeks) - 1;
            }

            $defaultSlot = $this->getDefaultSlotForWeek($weekId, $day, $slot);
            $defaultProgramId = $defaultSlot['programId'] ?? null;

            if ($programId === $defaultProgramId && $meta === null) {
                $this->removeSlot($userWeeks[$index]['slots'], $day, $slot);
            } else {
                $this->setSlot($userWeeks[$index]['slots'], $day, $slot, $programId, $meta);
            }

            if (empty($userWeeks[$index]['slots']) && ! array_key_exists('linkedTo', $userWeeks[$index])) {
                $this->removeUserWeekOverride($userWeeks, $weekId);
            }

            $this->trainingPlan->config->set("users.{$this->user}.schedule.weeks", array_values($userWeeks));
        }

        $this->trainingPlan->save();
        $this->trainingPlan->refresh();
        unset($this->schedule);
        unset($this->defaultSchedule);
        $this->notifyChanged('schedule');
    }

    protected function getDefaultSlotForWeek(string $weekId, int $day, int $slot): ?array
    {
        $defaultWeeks = $this->trainingPlan->config->get('default.schedule.weeks', []);
        $week = collect($defaultWeeks)->firstWhere('id', $weekId);

        if (! $week) {
            return null;
        }

        if ($week['linkedTo'] !== null) {
            return $this->getDefaultSlotForWeek($week['linkedTo'], $day, $slot);
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
                $weeks = $this->trainingPlan->config->get('default.schedule.weeks', []);
                foreach ($weeks as &$week) {
                    if ($week['linkedTo'] !== null) {
                        continue;
                    }
                    $week['slots'] = array_values(array_filter(
                        $week['slots'] ?? [],
                        fn ($s) => ($s['programId'] ?? null) !== $programId
                    ));
                }
                $this->trainingPlan->config->set('default.schedule.weeks', $weeks);
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
        if (! $week || $week['linkedTo'] !== null) {
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

        if ($week1['linkedTo'] !== null || $week2['linkedTo'] !== null) {
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

        $this->trainingPlan->config->set('default.schedule.weeks', $weeks);
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
