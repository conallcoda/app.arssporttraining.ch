<?php

namespace App\Livewire\Cms;

use App\Cms\Form\Form;
use App\Cms\Livewire\Concerns\InteractsWithFormData;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class FormModal extends Component
{
    use InteractsWithFormData;

    public array $data = [];

    public string $name;

    public string $title;

    public ?string $formDataClass = null;

    public string $submitLabel = 'Save';

    public string $cancelLabel = 'Cancel';

    public bool $flyout = true;

    public string $width = 'w-96';

    public ?string $activeTitle = null;

    public int $openCount = 0;

    public function mount(
        string $name,
        string $title,
        ?string $formDataClass = null,
        string $submitLabel = 'Save',
        string $cancelLabel = 'Cancel',
        bool $flyout = true,
        string $width = 'w-96',
    ): void {
        $this->name = $name;
        $this->title = $title;
        $this->formDataClass = $formDataClass;
        $this->submitLabel = $submitLabel;
        $this->cancelLabel = $cancelLabel;
        $this->flyout = $flyout;
        $this->width = $width;
        $this->data = $this->buildDefaultsFromFieldsets();
    }

    #[Computed]
    public function formConfig(): Form
    {
        if ($this->formDataClass) {
            if (method_exists($this->formDataClass, 'getForm')) {
                $definition = $this->formDataClass::getForm();

                return $definition instanceof Form ? $definition : Form::fields($definition);
            }

            if (method_exists($this->formDataClass, 'getFields')) {
                return Form::fields($this->formDataClass::getFields());
            }
        }

        return Form::fields([]);
    }

    #[Computed]
    public function fieldsets(): array
    {
        return $this->formConfig->resolveFieldsets($this->data);
    }

    /** @return array<string, string> */
    public function getListeners(): array
    {
        return [
            "open-{$this->name}" => 'open',
        ];
    }

    public function open(array $data = [], ?string $title = null, ?string $focusField = null, ?int $focusIndex = null): void
    {
        $this->activeTitle = $title;

        unset($this->formConfig, $this->fieldsets);
        $this->openCount++;

        if (empty($data)) {
            $this->data = $this->buildDefaultsFromFieldsets();
        } else {
            $this->data = $data;
            unset($this->fieldsets);
            $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $data);
        }

        $this->ensureRelationshipItemsHaveKeys();

        Flux::modal($this->name)->show();

        if ($focusField) {
            $this->dispatch('focus-field', field: $focusField, index: $focusIndex);
        }
    }

    public function submit(): void
    {
        $this->validate($this->buildValidationRulesFromFieldsets(), [
            'required' => 'This field is required.',
        ]);

        Flux::modal($this->name)->close();

        $this->dispatch("{$this->name}.submitted", data: $this->data);
    }

    public function cancel(): void
    {
        Flux::modal($this->name)->close();

        $this->dispatch("{$this->name}.cancelled");
    }

    public function render(): View
    {
        return view('livewire.components.form-modal');
    }
}
