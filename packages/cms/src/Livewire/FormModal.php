<?php

namespace Coda\Cms\Livewire;

use Coda\Cms\Form\Form;
use Coda\Cms\Livewire\Concerns\InteractsWithFormData;
use Coda\Cms\Livewire\Concerns\InteractsWithMediaUploads;
use Coda\Cms\Models\Contracts\PersistsWithMedia;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\HasMedia;

class FormModal extends Component
{
    use InteractsWithFormData;
    use InteractsWithMediaUploads;
    use WithFileUploads;

    public array $data = [];

    public string $name;

    public string $title;

    public ?string $formDataClass = null;

    public string $submitLabel = 'Save';

    public string $cancelLabel = 'Cancel';

    public bool $flyout = true;

    public string $maxWidth = 'max-w-sm';

    public bool $showDelete = false;

    public ?string $activeTitle = null;

    public int $openCount = 0;

    public function mount(
        string $name,
        string $title,
        ?string $formDataClass = null,
        string $submitLabel = 'Save',
        string $cancelLabel = 'Cancel',
        bool $flyout = true,
        string $maxWidth = 'max-w-sm',
        bool $showDelete = false,
    ): void {
        $this->name = $name;
        $this->title = $title;
        $this->formDataClass = $formDataClass;
        $this->submitLabel = $submitLabel;
        $this->cancelLabel = $cancelLabel;
        $this->flyout = $flyout;
        $this->maxWidth = $maxWidth;
        $this->showDelete = $showDelete;
    }

    protected function getFormDataClass(): ?string
    {
        return $this->formDataClass;
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
        $this->clearAllMediaState();

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
        $this->loadExistingMediaFromData();

        Flux::modal($this->name)->show();

        if ($focusField) {
            $this->dispatch('focus-field', field: $focusField, index: $focusIndex);
        }
    }

    protected function loadExistingMediaFromData(): void
    {
        if (! $this->hasFileUploadFields()) {
            return;
        }

        if (empty($this->data['id'])) {
            return;
        }

        $dataClass = $this->getFormDataClass();

        if (! $dataClass || ! is_subclass_of($dataClass, PersistsWithMedia::class)) {
            return;
        }

        $model = $dataClass::resolveModel((int) $this->data['id']);

        if ($model instanceof HasMedia) {
            $this->loadAllExistingMedia($model);
        }
    }

    public function submit(): void
    {
        $rules = array_merge(
            $this->buildValidationRulesFromFieldsets(),
            $this->buildMediaValidationRules()
        );

        $this->validate($rules, [
            'required' => 'This field is required.',
        ], $this->buildValidationAttributesFromFieldsets());

        $this->data = self::castEmptyStringsToNull($this->data);

        $dataClass = $this->getFormDataClass();
        $hasMedia = $this->hasFileUploadFields()
            && $dataClass
            && is_subclass_of($dataClass, PersistsWithMedia::class);

        $submittedData = $this->data;

        if ($hasMedia) {
            $isNew = empty($this->data['id']);
            $dataInstance = $dataClass::from($this->data);
            $model = $dataInstance->persist();

            if ($model instanceof HasMedia) {
                $this->persistAllMedia($model);
            }

            $submittedData = array_merge($this->data, [
                'id' => $dataInstance->id ?? $this->data['id'] ?? null,
                '_persisted' => true,
                '_isNew' => $isNew,
            ]);
        }

        Flux::modal($this->name)->close();

        $this->dispatch("{$this->name}.submitted", data: $submittedData);
    }

    public function requestDelete(): void
    {
        $this->dispatch("{$this->name}.delete-requested", data: $this->data);
    }

    public function cancel(): void
    {
        Flux::modal($this->name)->close();

        $this->dispatch("{$this->name}.cancelled");
    }

    protected static function castEmptyStringsToNull(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::castEmptyStringsToNull($value);
            } elseif ($value === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }

    public function render(): View
    {
        return view('cms::form-modal');
    }
}
