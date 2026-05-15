<?php

namespace App\Livewire\Training;

use App\Data\Training\ExerciseProgramData;
use Coda\Cms\Livewire\FormModal;
use Coda\FormKit\Form;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class ExerciseProgramFormModal extends FormModal
{
    public bool $showTrainingProgramStatus = false;

    protected function getFormDataClass(): ?string
    {
        return ExerciseProgramData::class;
    }

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
        $this->showTrainingProgramStatus = (bool) ($data['_show_training_program_status'] ?? false);
        unset($data['_show_training_program_status']);

        parent::open($data, $title, $focusField, $focusIndex, $formTypes, $activeFormType, $formTypeData);
    }

    #[Computed]
    public function formConfig(): Form
    {
        $excludeId = ! empty($this->data['id']) ? (int) $this->data['id'] : null;
        $definition = ExerciseProgramData::getForm($excludeId);

        return $definition instanceof Form ? $definition : Form::fields($definition);
    }

    public function render(): View
    {
        return view('livewire.training.exercise-program-form-modal');
    }
}
