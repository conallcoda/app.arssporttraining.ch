<?php

namespace App\Livewire\Training;

use App\Data\Training\ExerciseProgramData;
use Coda\Cms\Livewire\FormModal;
use Coda\FormKit\Form;
use Livewire\Attributes\Computed;

class ExerciseProgramFormModal extends FormModal
{
    protected function getFormDataClass(): ?string
    {
        return ExerciseProgramData::class;
    }

    #[Computed]
    public function formConfig(): Form
    {
        $excludeId = ! empty($this->data['id']) ? (int) $this->data['id'] : null;
        $definition = ExerciseProgramData::getForm($excludeId);

        return $definition instanceof Form ? $definition : Form::fields($definition);
    }
}
