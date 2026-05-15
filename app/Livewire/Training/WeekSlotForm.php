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

    public ?int $preselectedUserId = null;

    public function open(
        array $data = [],
        ?string $title = null,
        ?string $focusField = null,
        ?int $focusIndex = null,
        array $formTypes = [],
        ?string $activeFormType = null,
        array $formTypeData = [],
    ): void
    {
        $this->activeTitle = $title;
        $this->groupId = $data['groupId'] ?? null;
        $this->userId = $data['userId'] ?? null;
        $this->slotDate = $data['date'] ?? null;
        $this->preselectedUserId = $data['preselectedUserId'] ?? null;

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

        $this->loadMembers();

        Flux::modal($this->name)->show();
    }

    protected function loadMembers(): void
    {
        $this->members = [];
        $this->selectedMembers = [];

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

        if ($this->preselectedUserId !== null) {
            $this->selectedMembers = [(string) $this->preselectedUserId];

            return;
        }

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
    }

    public function submit(): void
    {
        $this->validate([
            ...$this->buildValidationRulesFromFieldsets(),
            'data.start_time' => 'required|date_format:H:i',
        ], [
            'required' => __('This field is required.'),
        ]);

        Flux::modal($this->name)->close();

        $allMemberIds = array_map('strval', array_column($this->members, 'id'));
        $deselectedMembers = array_values(array_diff($allMemberIds, $this->selectedMembers));

        $this->dispatch("{$this->name}.submitted", data: [
            ...$this->data,
            'date' => $this->slotDate,
            'deselected_members' => array_map('intval', $deselectedMembers),
            'selected_members' => array_map('intval', $this->selectedMembers),
            'original_training_program_id' => $this->originalTrainingProgramId,
            'original_start_time' => $this->originalStartTime,
        ]);
    }

    public function deleteSlot(): void
    {
        Flux::modal($this->name)->close();

        $this->dispatch("{$this->name}.deleted", data: [
            ...$this->data,
            'date' => $this->slotDate,
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
