<?php

namespace App\Livewire\Training;

use App\Data\Training\Calendar\WeekSlotData;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\UserGroup;
use Coda\Cms\Livewire\FormModal;
use Coda\FormKit\Form;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class WeekSlotForm extends FormModal
{
    public ?int $groupId = null;

    public ?int $userId = null;

    public ?string $slotDate = null;

    public bool $isEditing = false;

    public ?int $originalTrainingProgramId = null;

    public ?string $originalStartTime = null;

    public array $members = [];

    public array $selectedMembers = [];

    public array $originalSelectedMembers = [];

    public array $pendingSubmission = [];

    public bool $deleteConfirmationPending = false;

    public function mount(
        string $name = 'week-slot',
        string $title = 'add-slot-default',
        ?string $formDataClass = null,
        string $submitLabel = 'save-default',
        string $cancelLabel = 'cancel-default',
        bool $flyout = true,
        string $maxWidth = 'max-w-sm',
        bool $showDelete = false,
        array $contextData = [],
        array $excludeFields = [],
        array $formTypes = [],
        bool $persistOnSubmit = false,
    ): void {
        parent::mount(
            name: $name,
            title: $title === 'add-slot-default' ? __('Add Slot') : $title,
            formDataClass: $formDataClass,
            submitLabel: $submitLabel === 'save-default' ? __('Save') : $submitLabel,
            cancelLabel: $cancelLabel === 'cancel-default' ? __('Cancel') : $cancelLabel,
            flyout: $flyout,
            maxWidth: $maxWidth,
            showDelete: $showDelete,
            contextData: $contextData,
            excludeFields: $excludeFields,
            formTypes: $formTypes,
            persistOnSubmit: $persistOnSubmit,
        );
    }

    public function open(
        array $data = [],
        ?string $title = null,
        ?string $focusField = null,
        ?int $focusIndex = null,
        array $formTypes = [],
        ?string $activeFormType = null,
        array $formTypeData = [],
        ?string $actionName = null,
    ): void {
        $this->activeTitle = $title;
        $this->activeActionName = $actionName;
        $this->groupId = $data['groupId'] ?? null;
        $this->userId = $data['userId'] ?? null;
        $this->slotDate = $data['date'] ?? null;

        $isPrefill = $data['prefill'] ?? false;
        $this->isEditing = ! $isPrefill && isset($data['training_program_id']) && $data['training_program_id'] !== null;

        unset($this->formConfig, $this->fieldsets);
        $this->openCount++;

        $this->originalTrainingProgramId = $isPrefill ? null : ($data['training_program_id'] ?? null);
        $this->originalStartTime = $isPrefill ? null : ($data['start_time'] ?? null);

        $this->data = [
            'training_program_id' => $data['training_program_id'] ?? null,
            'start_time' => $data['start_time'] ?? '09:00',
        ];
        $this->pendingSubmission = [];
        $this->deleteConfirmationPending = false;

        $this->loadMembers();

        Flux::modal($this->name)->show();
    }

    protected function loadMembers(): void
    {
        $this->members = [];
        $this->selectedMembers = [];
        $this->originalSelectedMembers = [];

        if ($this->groupId === null || $this->userId !== null) {
            return;
        }

        $group = UserGroup::with('members')->find($this->groupId);
        if ($group === null) {
            return;
        }

        $this->members = $group->members->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
        ])->all();

        $allMemberIds = array_column($this->members, 'id');

        if (! $this->isEditing || $this->data['training_program_id'] === null) {
            $this->selectedMembers = array_map('strval', $allMemberIds);

            return;
        }

        $datetime = $this->slotDate.' '.$this->data['start_time'].':00';

        $usersWithSlot = TrainingProgramSlot::query()
            ->where('training_program_id', $this->data['training_program_id'])
            ->where('datetime', $datetime)
            ->whereIn('user_id', $allMemberIds)
            ->pluck('user_id')
            ->all();

        $this->selectedMembers = array_map('strval', $usersWithSlot);
        $this->originalSelectedMembers = $this->selectedMembers;
    }

    public function submit(): void
    {
        $this->validate([
            ...$this->buildValidationRulesFromFieldsets(),
            'data.start_time' => 'required|date_format:H:i',
        ], [
            'required' => __('This field is required.'),
        ]);

        $allMemberIds = array_map('strval', array_column($this->members, 'id'));
        $selectedMembers = array_values(array_intersect(
            array_map('strval', $this->selectedMembers),
            $allMemberIds,
        ));
        $removedMembers = $this->isEditing
            ? array_values(array_diff($this->originalSelectedMembers, $selectedMembers))
            : [];
        $payload = [
            ...$this->data,
            'date' => $this->slotDate,
            'operation_mode' => $this->isEditing ? 'edit' : 'create',
            'deselected_members' => array_map('intval', $removedMembers),
            'selected_members' => array_map('intval', $selectedMembers),
            'original_selected_members' => array_map('intval', $this->originalSelectedMembers),
            'original_training_program_id' => $this->originalTrainingProgramId,
            'original_start_time' => $this->originalStartTime,
            'removals_confirmed' => false,
        ];

        if ($removedMembers !== []) {
            $this->pendingSubmission = $payload;
            Flux::modal('confirm-week-slot-removals')->show();

            return;
        }

        $this->dispatchSubmission($payload);
    }

    public function confirmGroupRemovals(): void
    {
        if ($this->pendingSubmission === []) {
            return;
        }

        $payload = [
            ...$this->pendingSubmission,
            'removals_confirmed' => true,
        ];
        $this->pendingSubmission = [];

        Flux::modal('confirm-week-slot-removals')->close();
        $this->dispatchSubmission($payload);
    }

    public function pendingRemovalHeading(): string
    {
        $count = count($this->pendingSubmission['deselected_members'] ?? []);

        return trans_choice(
            'Remove :count pending session?|Remove :count pending sessions?',
            $count,
            ['count' => $count],
        );
    }

    public function pendingRemovalDescription(): string
    {
        $removedIds = array_map('intval', $this->pendingSubmission['deselected_members'] ?? []);
        $names = collect($this->members)
            ->filter(fn (array $member): bool => in_array((int) $member['id'], $removedIds, true))
            ->pluck('name')
            ->join(', ');

        return __('This will remove the pending session for :athletes. Recorded sessions remain protected.', [
            'athletes' => $names,
        ]);
    }

    /** @param array<string, mixed> $payload */
    protected function dispatchSubmission(array $payload): void
    {
        Flux::modal($this->name)->close();
        $this->dispatch("{$this->name}.submitted", data: $payload);
    }

    public function deleteSlot(): void
    {
        $this->deleteConfirmationPending = true;
        Flux::modal('confirm-week-slot-delete')->show();
    }

    public function confirmDeleteSlot(): void
    {
        if (! $this->deleteConfirmationPending) {
            return;
        }

        $this->deleteConfirmationPending = false;
        Flux::modal('confirm-week-slot-delete')->close();
        Flux::modal($this->name)->close();

        $this->dispatch("{$this->name}.deleted", data: [
            ...$this->data,
            'date' => $this->slotDate,
            'deletion_confirmed' => true,
        ]);
    }

    public function pendingDeleteHeading(): string
    {
        $count = max(1, count($this->originalSelectedMembers));

        return trans_choice(
            'Remove this pending session?|Remove :count pending sessions?',
            $count,
            ['count' => $count],
        );
    }

    public function pendingDeleteDescription(): string
    {
        if ($this->userId !== null || $this->originalSelectedMembers === []) {
            return __('This will remove this pending session. Recorded sessions remain protected.');
        }

        $originalIds = array_map('intval', $this->originalSelectedMembers);
        $names = collect($this->members)
            ->filter(fn (array $member): bool => in_array((int) $member['id'], $originalIds, true))
            ->pluck('name')
            ->join(', ');

        return __('This will remove the pending occurrence for: :athletes. Recorded sessions remain protected.', [
            'athletes' => $names,
        ]);
    }

    #[Computed]
    public function formConfig(): Form
    {
        return WeekSlotData::getForm($this->groupId, $this->slotDate);
    }

    public function render(): View
    {
        return view('livewire.training.week-slot-form');
    }
}
