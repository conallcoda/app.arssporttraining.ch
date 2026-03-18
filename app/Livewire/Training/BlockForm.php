<?php

namespace App\Livewire\Training;

use App\Data\Training\Calendar\BlockFormData;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramBlockTypeEnum;
use App\Models\Users\UserGroup;
use Coda\Cms\Form\Form;
use Coda\Cms\Livewire\FormModal;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class BlockForm extends FormModal
{
    public ?int $groupId = null;

    public ?int $userId = null;

    public ?int $editingBlockId = null;

    public bool $isEditing = false;

    public array $members = [];

    public array $selectedMembers = [];

    public function mount(
        string $name = 'block',
        string $title = 'add-block-default',
        ?string $formDataClass = null,
        string $submitLabel = 'save-default',
        string $cancelLabel = 'cancel-default',
        bool $flyout = true,
        string $maxWidth = 'max-w-sm',
        bool $showDelete = false,
        array $excludeFields = [],
    ): void {
        parent::mount(
            name: $name,
            title: $title === 'add-block-default' ? __('Add Block') : $title,
            formDataClass: $formDataClass,
            submitLabel: $submitLabel === 'save-default' ? __('Save') : $submitLabel,
            cancelLabel: $cancelLabel === 'cancel-default' ? __('Cancel') : $cancelLabel,
            flyout: $flyout,
            maxWidth: $maxWidth,
            showDelete: $showDelete,
            excludeFields: $excludeFields,
        );
    }

    public function open(array $data = [], ?string $title = null, ?string $focusField = null, ?int $focusIndex = null): void
    {
        $this->activeTitle = $title;
        $this->groupId = $data['groupId'] ?? null;
        $this->userId = $data['userId'] ?? null;
        $this->editingBlockId = $data['blockId'] ?? null;
        $this->isEditing = $this->editingBlockId !== null;

        unset($this->formConfig, $this->fieldsets);
        $this->openCount++;

        $defaultType = 'focus';
        $defaultColor = TrainingProgramBlockTypeEnum::from($defaultType)->blockTypeClass()::defaultColor();

        $this->data = [
            'type' => $defaultType,
            'start' => $data['date'] ?? '',
            'end' => '',
            'note' => '',
            'color' => $defaultColor,
        ];

        if ($this->isEditing) {
            $existingBlock = TrainingProgramBlock::find($this->editingBlockId);
            if ($existingBlock) {
                $this->data = [
                    'type' => $existingBlock->type->value,
                    'start' => $existingBlock->start->format('Y-m-d'),
                    'end' => $existingBlock->end?->format('Y-m-d') ?? '',
                    'note' => $existingBlock->note,
                    'color' => $existingBlock->color,
                ];
            }
        }

        $this->loadMembers();

        Flux::modal($this->name)->show();
    }

    public function updatedDataType(string $value): void
    {
        $this->data['color'] = TrainingProgramBlockTypeEnum::from($value)->blockTypeClass()::defaultColor();

        unset($this->formConfig, $this->fieldsets);
        $this->openCount++;
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

        if (! $this->isEditing) {
            $this->selectedMembers = array_map('strval', $allMemberIds);

            return;
        }

        $existingBlock = TrainingProgramBlock::find($this->editingBlockId);
        if ($existingBlock === null) {
            $this->selectedMembers = array_map('strval', $allMemberIds);

            return;
        }

        $usersWithBlock = TrainingProgramBlock::query()
            ->where('group_id', $this->groupId)
            ->where('type', $existingBlock->type)
            ->where('start', $existingBlock->start)
            ->where('note', $existingBlock->note)
            ->whereIn('user_id', $allMemberIds)
            ->pluck('user_id')
            ->all();

        $this->selectedMembers = array_map('strval', $usersWithBlock);
    }

    public function submit(): void
    {
        $this->validate([
            ...$this->buildValidationRulesFromFieldsets(),
        ], [
            'required' => __('This field is required.'),
        ]);

        Flux::modal($this->name)->close();

        $allMemberIds = array_map('strval', array_column($this->members, 'id'));
        $deselectedMembers = array_values(array_diff($allMemberIds, $this->selectedMembers));

        $this->dispatch("{$this->name}.submitted", data: [
            ...$this->data,
            'selected_members' => array_map('intval', $this->selectedMembers),
            'deselected_members' => array_map('intval', $deselectedMembers),
            'editing_block_id' => $this->editingBlockId,
            'groupId' => $this->groupId,
            'userId' => $this->userId,
        ]);
    }

    public function deleteBlock(): void
    {
        Flux::modal($this->name)->close();

        $this->dispatch("{$this->name}.deleted", data: [
            'editing_block_id' => $this->editingBlockId,
            'groupId' => $this->groupId,
            'userId' => $this->userId,
        ]);
    }

    #[Computed]
    public function formConfig(): Form
    {
        return BlockFormData::getForm();
    }

    public function render(): View
    {
        return view('livewire.training.block-form');
    }
}
