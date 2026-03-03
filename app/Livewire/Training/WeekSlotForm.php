<?php

namespace App\Livewire\Training;

use App\Data\Training\Calendar\WeekSlotData;
use Coda\Cms\Form\Form;
use Coda\Cms\Livewire\FormModal;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class WeekSlotForm extends FormModal
{
    public ?int $groupId = null;

    public ?int $userId = null;

    public ?string $slotDate = null;

    public bool $isEditing = false;

    public function mount(
        string $name = 'week-slot',
        string $title = 'Add Slot',
        ?string $formDataClass = null,
        string $submitLabel = 'Save',
        string $cancelLabel = 'Cancel',
        bool $flyout = true,
        string $maxWidth = 'max-w-sm',
        bool $showDelete = false,
    ): void {
        parent::mount(
            name: $name,
            title: $title,
            formDataClass: $formDataClass,
            submitLabel: $submitLabel,
            cancelLabel: $cancelLabel,
            flyout: $flyout,
            maxWidth: $maxWidth,
            showDelete: $showDelete,
        );
    }

    public function open(array $data = [], ?string $title = null, ?string $focusField = null, ?int $focusIndex = null): void
    {
        $this->activeTitle = $title;
        $this->groupId = $data['groupId'] ?? null;
        $this->userId = $data['userId'] ?? null;
        $this->slotDate = $data['date'] ?? null;
        $this->isEditing = isset($data['training_program_id']) && $data['training_program_id'] !== null;

        unset($this->formConfig, $this->fieldsets);
        $this->openCount++;

        $this->data = [
            'training_program_id' => $data['training_program_id'] ?? null,
            'start_time' => $data['start_time'] ?? '09:00',
        ];

        Flux::modal($this->name)->show();
    }

    public function submit(): void
    {
        $this->validate([
            ...$this->buildValidationRulesFromFieldsets(),
            'data.start_time' => 'required|date_format:H:i',
        ], [
            'required' => 'This field is required.',
        ]);

        Flux::modal($this->name)->close();

        $this->dispatch("{$this->name}.submitted", data: [
            ...$this->data,
            'date' => $this->slotDate,
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
        return WeekSlotData::getForm($this->groupId, $this->userId, $this->slotDate);
    }

    public function render(): View
    {
        return view('livewire.training.week-slot-form');
    }
}
