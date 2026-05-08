<?php

namespace Coda\Cms\Livewire\Concerns;

use Coda\Cms\Data\AbstractData;
use Coda\Cms\Registry;
use Coda\FormKit\Field;
use Coda\FormKit\Fields\Relationship;
use Coda\FormKit\Form;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;

trait InteractsWithEntityDefinition
{
    public ?string $scopeAreaKey = null;

    public string|int|null $scopeContextRouteValue = null;

    abstract protected function getDataClass(): string;

    abstract protected function getBaseQuery();

    protected function dataFromModel(Model $model): AbstractData
    {
        return $this->getDataClass()::from($model);
    }

    protected function createDataFromForm(array $formData): AbstractData
    {
        return $this->getDataClass()::from(array_replace($formData, $this->getFormContextData()));
    }

    protected function getFormDefinition(): Form|array
    {
        $dataClass = $this->getDataClass();

        if (method_exists($dataClass, 'getForm')) {
            return $dataClass::getForm();
        }

        if (method_exists($dataClass, 'getFields')) {
            return $dataClass::getFields();
        }

        return [];
    }

    #[Computed]
    public function formConfig(): Form
    {
        $definition = $this->getFormDefinition();

        if ($definition instanceof Form) {
            return $definition;
        }

        return Form::fields($definition);
    }

    #[Computed]
    public function fields(): array
    {
        return $this->formConfig->getFields();
    }

    #[Computed]
    public function fieldsets(): array
    {
        return $this->formConfig->resolveFieldsets($this->data);
    }

    protected function getFormRelationshipsToLoad(): array
    {
        return collect($this->getAllFields())
            ->filter(fn (Field $field) => $field instanceof Relationship)
            ->map(fn (Field $field) => $field->name)
            ->values()
            ->all();
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    protected function currentScopeContext(): mixed
    {
        if ($this->scopeAreaKey !== null) {
            $area = app(Registry::class)->area($this->scopeAreaKey);

            if ($area?->isScoped()) {
                return $area->resolveCurrentContext($this->scopeContextRouteValue);
            }
        }

        return app(Registry::class)->currentContext();
    }

    protected function currentScopeValue(string $path = 'id', mixed $default = null): mixed
    {
        $context = $this->currentScopeContext();

        if ($context instanceof \Illuminate\Database\Eloquent\Model && ($path === 'id' || $path === $context->getKeyName())) {
            return $context->getKey();
        }

        return data_get($context, $path, $default);
    }

    protected function captureScopeSnapshot(): void
    {
        if ($this->scopeAreaKey !== null) {
            return;
        }

        $registry = app(Registry::class);
        $area = $registry->currentArea();

        if (! $area?->isScoped()) {
            return;
        }

        $context = $registry->currentContext();

        if ($context === null) {
            return;
        }

        $this->scopeAreaKey = $area->key;
        $this->scopeContextRouteValue = $area->contextRouteValue($context);
    }

    protected function getFormContextBindings(): array
    {
        return [];
    }

    protected function getFormContextData(): array
    {
        $context = $this->currentScopeContext();

        if ($context === null) {
            return [];
        }

        $data = [];

        foreach ($this->getFormContextBindings() as $field => $binding) {
            $data[$field] = is_callable($binding)
                ? $binding($context, $this)
                : data_get($context, $binding);
        }

        return array_filter($data, fn (mixed $value) => $value !== null);
    }

    protected function getFormExcludedFields(): array
    {
        return array_keys($this->getFormContextBindings());
    }

    protected function scopeToCurrentContext(Builder $query, string $column, string|callable $binding = 'id'): Builder
    {
        $context = $this->currentScopeContext();

        if ($context === null) {
            return $query;
        }

        $value = is_callable($binding)
            ? $binding($context, $this)
            : data_get($context, $binding);

        if ($value !== null && $value !== '') {
            $query->where($column, $value);
        }

        return $query;
    }

    protected function mountEntityDefaults(): void
    {
        $this->captureScopeSnapshot();
        $this->options = array_merge($this->getDefaultOptions(), $this->options);
        $this->data = array_replace($this->buildDefaultsFromFieldsets(), $this->getFormContextData());
    }

    protected function openEditFromUrl(): void
    {
        $model = $this->getBaseQuery()->find($this->edit);

        if (! $model) {
            $this->edit = null;

            return;
        }

        $data = $this->dataFromModel($model)->toArray();

        $this->dispatch("open-{$this->editModalName}", data: $data, title: 'Edit '.$this->getEntityName());
    }

    public function startEdit(int $id): void
    {
        $this->edit = $id;

        $model = $this->getBaseQuery()->findOrFail($id);
        $data = $this->dataFromModel($model)->toArray();

        $this->dispatch("open-{$this->editModalName}", data: $data, title: 'Edit '.$this->getEntityName());
    }

    protected function emit(): void {}
}
