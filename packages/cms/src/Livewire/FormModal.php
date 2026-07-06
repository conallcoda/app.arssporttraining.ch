<?php

namespace Coda\Cms\Livewire;

use Coda\Cms\Livewire\Concerns\InteractsWithFormData;
use Coda\Cms\Livewire\Concerns\InteractsWithMediaUploads;
use Coda\Cms\Models\Contracts\PersistsWithMedia;
use Coda\Cms\Models\Contracts\ResolvesMediaModel;
use Coda\FormKit\Form;
use Coda\FormKit\Field;
use Coda\FormKit\FormFieldset;
use Coda\FormKit\FormFieldsetGroup;
use Flux\Flux;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;
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

    public array $formTypes = [];

    public array $formTypeData = [];

    public ?string $activeFormType = null;

    public string $submitLabel = 'Save';

    public string $cancelLabel = 'Cancel';

    public bool $flyout = true;

    public string $maxWidth = 'max-w-sm';

    public bool $showDelete = false;

    public array $contextData = [];

    public ?string $activeTitle = null;

    public array $excludeFields = [];

    public int $openCount = 0;

    public bool $persistOnSubmit = false;

    public array $pendingFieldCreate = [];

    public string $activeFieldsetTab = '';

    public function mount(
        string $name,
        string $title,
        ?string $formDataClass = null,
        string $submitLabel = 'Save',
        string $cancelLabel = 'Cancel',
        bool $flyout = true,
        string $maxWidth = 'max-w-sm',
        bool $showDelete = false,
        array $contextData = [],
        array $excludeFields = [],
        array $formTypes = [],
        bool $persistOnSubmit = false,
    ): void {
        $this->name = $name;
        $this->title = $title;
        $this->formDataClass = $formDataClass;
        $this->submitLabel = $submitLabel;
        $this->cancelLabel = $cancelLabel;
        $this->flyout = $flyout;
        $this->maxWidth = $maxWidth;
        $this->showDelete = $showDelete;
        $this->contextData = $contextData;
        $this->excludeFields = $excludeFields;
        $this->formTypes = $formTypes;
        $this->persistOnSubmit = $persistOnSubmit;
    }

    protected function getFormDataClass(): ?string
    {
        if ($this->activeFormType !== null) {
            return $this->activeFormTypeConfig()['formDataClass'] ?? null;
        }

        return $this->formDataClass;
    }

    public function activeFormTypeConfig(): ?array
    {
        if ($this->activeFormType === null) {
            return null;
        }

        return collect($this->formTypes)->firstWhere('key', $this->activeFormType);
    }

    #[Computed]
    public function formConfig(): Form
    {
        $dataClass = $this->getFormDataClass();

        if ($dataClass) {
            if (method_exists($dataClass, 'getForm')) {
                $definition = $dataClass::getForm();

                return $definition instanceof Form ? $definition : Form::fields($definition);
            }

            if (method_exists($dataClass, 'getFields')) {
                return Form::fields($dataClass::getFields());
            }
        }

        return Form::fields([]);
    }

    #[Computed]
    public function fieldsets(): array
    {
        $fieldsets = $this->formConfig->resolveFieldsets($this->data);

        if (! empty($this->excludeFields)) {
            foreach ($fieldsets as $fieldset) {
                if ($fieldset instanceof FormFieldset) {
                    $fieldset->fields(
                        array_values(array_filter(
                            $fieldset->fields,
                            fn ($field) => ! in_array($field->name, $this->excludeFields, true)
                        ))
                    );
                }
            }

            $fieldsets = array_values(array_filter($fieldsets, function ($fieldset) {
                return ! $fieldset instanceof FormFieldset || ! empty($fieldset->fields);
            }));
        }

        return $fieldsets;
    }

    /** @return array<string, string> */
    public function getListeners(): array
    {
        $listeners = [
            "open-{$this->name}" => 'open',
        ];

        foreach ($this->fieldCreateModals() as $modal) {
            $listeners["{$modal['name']}.submitted"] = 'handleFieldCreateSubmitted';
        }

        return $listeners;
    }

    public function fieldCreateModals(): array
    {
        return collect($this->getAllFields())
            ->filter(fn ($field) => method_exists($field, 'hasCreateOption') && $field->hasCreateOption())
            ->map(fn ($field) => [
                'fieldName' => $field->name,
                'name' => $this->fieldCreateModalName($field->name),
                'title' => $field->getCreateOptionModalTitle(),
                'formDataClass' => $field->createOptionFormDataClass,
                'submitLabel' => $field->createOptionSubmitLabel,
                'contextData' => $field->resolveCreateOptionContextData($this->data),
                'excludeFields' => $field->createOptionExcludeFields,
            ])
            ->unique('fieldName')
            ->values()
            ->all();
    }

    public function fieldCreateModalName(string $fieldName): string
    {
        return "{$this->name}-field-create-{$fieldName}";
    }

    public function openSelectCreateModal(string $fieldName, string $targetPath, ?string $search = null): void
    {
        $field = collect($this->getAllFields())->firstWhere('name', $fieldName);

        if (! $field || ! method_exists($field, 'hasCreateOption') || ! $field->hasCreateOption()) {
            return;
        }

        $this->pendingFieldCreate = [
            'fieldName' => $fieldName,
            'targetPath' => $targetPath,
            'index' => null,
        ];

        $seedData = $field->resolveCreateOptionSeedData($search, $this->data, null, $this);

        $this->dispatch(
            'open-'.$this->fieldCreateModalName($fieldName),
            data: $seedData,
            title: $field->getCreateOptionModalTitle(),
        );
    }

    public function openRelationshipCreateModal(string $fieldName, string $targetPath, int $index, ?string $search = null): void
    {
        $field = collect($this->getAllFields())->firstWhere('name', $fieldName);

        if (! $field || ! method_exists($field, 'hasCreateOption') || ! $field->hasCreateOption()) {
            return;
        }

        $this->pendingFieldCreate = [
            'fieldName' => $fieldName,
            'targetPath' => $targetPath,
            'index' => $index,
        ];

        $seedData = $field->resolveCreateOptionSeedData($search, $this->data, $index, $this);

        $this->dispatch(
            'open-'.$this->fieldCreateModalName($fieldName),
            data: $seedData,
            title: $field->getCreateOptionModalTitle(),
        );
    }

    public function handleFieldCreateSubmitted(array $data): void
    {
        $fieldName = $this->pendingFieldCreate['fieldName'] ?? null;
        $targetPath = $this->pendingFieldCreate['targetPath'] ?? null;
        $index = $this->pendingFieldCreate['index'] ?? null;

        if (! is_string($fieldName) || ! is_string($targetPath)) {
            return;
        }

        $field = collect($this->getAllFields())->firstWhere('name', $fieldName);

        if (! $field || ! method_exists($field, 'hasCreateOption') || ! $field->hasCreateOption()) {
            return;
        }

        $attachedValue = $field->resolveCreatedOptionAttachment($data, $this->data, is_numeric($index) ? (int) $index : null, $this);

        if ($attachedValue !== null) {
            data_set($this, $targetPath, $attachedValue);
        }

        $this->pendingFieldCreate = [];
        unset($this->formConfig, $this->fieldsets);

        if ($field->createOptionCloseParentAfterSubmit) {
            Flux::modal($this->name)->close();
        }
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
        $this->activeTitle = $title;
        $this->activeFieldsetTab = '';
        $this->resetValidation();
        $this->clearAllMediaState();
        $this->formTypes = $formTypes;
        $this->formTypeData = $formTypeData;
        $this->activeFormType = $activeFormType
            ?? collect($this->formTypes)->first()['key']
            ?? null;

        unset($this->formConfig, $this->fieldsets);
        $this->openCount++;

        $this->hydrateDataForOpen($data);
        $this->activeFieldsetTab = $this->topLevelFieldsetTabNames()[0] ?? '';

        $this->ensureRelationshipItemsHaveKeys();
        $this->loadExistingMediaFromData();

        Flux::modal($this->name)->show();

        if ($focusField) {
            $this->dispatch('focus-field', field: $focusField, index: $focusIndex);
        }
    }

    public function updatedActiveFormType(?string $value): void
    {
        if ($value === null) {
            return;
        }

        $this->hydrateDataForOpen($this->formTypeData[$value] ?? []);
        $this->ensureRelationshipItemsHaveKeys();
    }

    protected function hydrateDataForOpen(array $data = []): void
    {
        if (empty($data)) {
            $seedData = $this->activeFormType !== null
                ? ($this->formTypeData[$this->activeFormType] ?? [])
                : [];

            $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $seedData, $this->contextData);
            unset($this->fieldsets);

            return;
        }

        $this->data = array_replace_recursive($data, $this->contextData);
        unset($this->fieldsets);
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $data, $this->contextData);
        unset($this->fieldsets);
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

        $model = is_subclass_of($dataClass, ResolvesMediaModel::class)
            ? $dataClass::resolveMediaModel((int) $this->data['id'])
            : $dataClass::resolveModel((int) $this->data['id']);

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

        try {
            $this->validate($rules, [
                'required' => 'This field is required.',
            ], $this->buildValidationAttributesFromFieldsets());
        } catch (ValidationException $exception) {
            $this->selectFieldsetTabForValidationErrors($exception->validator->errors()->keys());

            throw $exception;
        }

        $this->data = self::castEmptyStringsToNull($this->data);

        $dataClass = $this->getFormDataClass();
        $hasMedia = $this->hasFileUploadFields()
            && $dataClass
            && is_subclass_of($dataClass, PersistsWithMedia::class);
        $shouldPersist = $this->persistOnSubmit && $dataClass;

        $submittedData = $this->data;

        if ($this->activeFormType !== null) {
            $submittedData['_treeNodeType'] = $this->activeFormType;
        }

        if ($hasMedia || $shouldPersist) {
            $isNew = empty($this->data['id']);
            $dataInstance = $dataClass::from($this->data);
            $model = $dataInstance->persist();

            if ($hasMedia) {
                $mediaModel = is_subclass_of($dataClass, ResolvesMediaModel::class)
                    ? $dataClass::resolveMediaModel((int) ($dataInstance->id ?? $this->data['id'] ?? 0))
                    : $model;

                if ($mediaModel instanceof HasMedia) {
                    $this->persistAllMedia($mediaModel);
                }
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

    public function initializeTabState(string $default): void
    {
        $validTabs = $this->topLevelFieldsetTabNames();

        if ($default !== '' && ($this->activeFieldsetTab === '' || ($validTabs !== [] && ! in_array($this->activeFieldsetTab, $validTabs, true)))) {
            $this->activeFieldsetTab = $default;
        }
    }

    /**
     * @param  array<int, string>  $errorKeys
     */
    protected function selectFieldsetTabForValidationErrors(array $errorKeys): void
    {
        $tabOrder = $this->topLevelFieldsetTabNames();
        $errorTabs = $this->fieldsetTabsContainingErrors($errorKeys);

        if ($tabOrder === [] || $errorTabs === []) {
            return;
        }

        foreach ($tabOrder as $tab) {
            if (in_array($tab, $errorTabs, true)) {
                $this->activeFieldsetTab = $tab;

                return;
            }
        }
    }

    /**
     * @return array<int, string>
     */
    protected function topLevelFieldsetTabNames(): array
    {
        return collect($this->fieldsets)
            ->filter(fn ($item): bool => $item instanceof FormFieldsetGroup)
            ->flatMap(fn (FormFieldsetGroup $group): array => collect($group->fieldsets)
                ->map(fn ($item): ?string => $item instanceof FormFieldset || $item instanceof FormFieldsetGroup ? $item->name : null)
                ->filter(fn (?string $name): bool => is_string($name) && $name !== '')
                ->values()
                ->all())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $errorKeys
     * @return array<int, string>
     */
    protected function fieldsetTabsContainingErrors(array $errorKeys): array
    {
        $tabs = [];

        foreach ($this->fieldsets as $item) {
            if (! $item instanceof FormFieldsetGroup) {
                continue;
            }

            foreach ($item->fieldsets as $tab) {
                $tabName = $tab instanceof FormFieldset || $tab instanceof FormFieldsetGroup ? $tab->name : null;

                if (! is_string($tabName) || $tabName === '') {
                    continue;
                }

                $ruleKeys = array_keys($this->validationRulesForFieldsetItem($tab));

                if ($this->validationKeysContainAny($ruleKeys, $errorKeys)) {
                    $tabs[] = $tabName;
                }
            }
        }

        return array_values(array_unique($tabs));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validationRulesForFieldsetItem(FormFieldset|FormFieldsetGroup $item): array
    {
        if ($item instanceof FormFieldset) {
            $prefix = $item->prefix ?? 'data';

            return Field::buildValidationRules($item->fields, $prefix.'.', $this->data);
        }

        $rules = [];

        foreach ($item->fieldsets as $child) {
            if ($child instanceof FormFieldset || $child instanceof FormFieldsetGroup) {
                $rules = array_merge($rules, $this->validationRulesForFieldsetItem($child));
            }
        }

        return $rules;
    }

    /**
     * @param  array<int, string>  $ruleKeys
     * @param  array<int, string>  $errorKeys
     */
    protected function validationKeysContainAny(array $ruleKeys, array $errorKeys): bool
    {
        foreach ($ruleKeys as $ruleKey) {
            foreach ($errorKeys as $errorKey) {
                if ($ruleKey === $errorKey || fnmatch($ruleKey, $errorKey) || fnmatch($errorKey, $ruleKey)) {
                    return true;
                }
            }
        }

        return false;
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
