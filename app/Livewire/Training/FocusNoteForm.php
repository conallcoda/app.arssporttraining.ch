<?php

namespace App\Livewire\Training;

use App\Data\Training\Calendar\FocusNoteData;
use App\Models\Training\TrainingProgramNote;
use App\Models\Users\UserGroup;
use Coda\Cms\Form\Form;
use Coda\Cms\Livewire\FormModal;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class FocusNoteForm extends FormModal
{
    public ?int $groupId = null;

    public ?int $userId = null;

    public ?int $editingNoteId = null;

    public bool $isEditing = false;

    public array $members = [];

    public array $selectedMembers = [];

    public function mount(
        string $name = 'focus-note',
        string $title = 'add-focus-default',
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
            title: $title === 'add-focus-default' ? __('Add Focus') : $title,
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
        $this->editingNoteId = $data['noteId'] ?? null;
        $this->isEditing = $this->editingNoteId !== null;

        unset($this->formConfig, $this->fieldsets);
        $this->openCount++;

        $this->data = [
            'start' => $data['date'] ?? '',
            'end' => '',
            'note' => '',
        ];

        if ($this->isEditing) {
            $existingNote = TrainingProgramNote::find($this->editingNoteId);
            if ($existingNote) {
                $this->data = [
                    'start' => $existingNote->start->format('Y-m-d'),
                    'end' => $existingNote->end?->format('Y-m-d') ?? '',
                    'note' => $existingNote->note,
                ];
            }
        }

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

        if (! $this->isEditing) {
            $this->selectedMembers = array_map('strval', $allMemberIds);

            return;
        }

        $existingNote = TrainingProgramNote::find($this->editingNoteId);
        if ($existingNote === null) {
            $this->selectedMembers = array_map('strval', $allMemberIds);

            return;
        }

        $usersWithNote = TrainingProgramNote::query()
            ->where('group_id', $this->groupId)
            ->where('type', 'focus')
            ->where('start', $existingNote->start)
            ->where('note', $existingNote->note)
            ->whereIn('user_id', $allMemberIds)
            ->pluck('user_id')
            ->all();

        $this->selectedMembers = array_map('strval', $usersWithNote);
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
            'editing_note_id' => $this->editingNoteId,
            'groupId' => $this->groupId,
            'userId' => $this->userId,
        ]);
    }

    public function deleteNote(): void
    {
        Flux::modal($this->name)->close();

        $this->dispatch("{$this->name}.deleted", data: [
            'editing_note_id' => $this->editingNoteId,
            'groupId' => $this->groupId,
            'userId' => $this->userId,
        ]);
    }

    #[Computed]
    public function formConfig(): Form
    {
        return FocusNoteData::getForm();
    }

    public function render(): View
    {
        return view('livewire.training.focus-note-form');
    }
}
