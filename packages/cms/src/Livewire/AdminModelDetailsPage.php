<?php

namespace Coda\Cms\Livewire;

use Coda\Cms\AdminModule;
use Coda\Cms\DetailsPageDefinition;
use Coda\Cms\Registry;
use Coda\Cms\Layout\Layout;
use Coda\Cms\Livewire\Concerns\InteractsWithScopeContext;
use Coda\FormKit\Form;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;

class AdminModelDetailsPage extends AbstractModelDetailsPage
{
    use InteractsWithScopeContext;

    public function mount(mixed $record = null): void
    {
        $this->pageName = (string) request()->route()?->getName();
        $this->captureScopeSnapshot();

        abort_unless(is_numeric($record), 404);

        $this->record = (int) $record;
        $this->loadRecord();
    }

    protected function module(): AdminModule
    {
        $module = app(Registry::class)->moduleForPage($this->pageName);

        abort_unless($module instanceof AdminModule, 404);

        return $module;
    }

    #[Computed]
    public function pageDefinition(): DetailsPageDefinition
    {
        $page = app(Registry::class)->page($this->pageName);

        abort_unless($page instanceof DetailsPageDefinition, 404);

        return $page;
    }

    protected function dataClass(): string
    {
        return $this->module()->dataClassName();
    }

    #[Computed]
    public function formConfig(): Form
    {
        return $this->module()->resolveForm('cms_details');
    }

    #[Computed]
    public function detailsLayout(): ?Layout
    {
        return $this->module()->resolveDetails();
    }

    #[Computed]
    public function entityName(): string
    {
        return $this->module()->entityLabel();
    }

    #[Computed]
    public function pageTitle(): string
    {
        $field = $this->detailsTitleField();
        $value = $field ? data_get($this->data, $field) : null;

        return filled($value) ? (string) $value : $this->entityName;
    }

    protected function detailsTitleField(): ?string
    {
        $table = $this->module()->resolveTable();

        foreach ($table->getColumns() as $column) {
            if ($column->isTitleField()) {
                return $column->field;
            }
        }

        return null;
    }

    protected function resolveModel(int $id): ?Model
    {
        $dataClass = $this->dataClass();

        if (method_exists($dataClass, 'resolveModel')) {
            return $dataClass::resolveModel($id);
        }

        return $this->module()->query($this)->find($id);
    }

    public function scopeValue(string $path = 'id', mixed $default = null): mixed
    {
        return $this->currentScopeValue($path, $default);
    }
}
