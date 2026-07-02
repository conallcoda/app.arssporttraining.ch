<?php

namespace Coda\Cms\Livewire;

use Coda\Cms\AdminModule;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Display\Table;
use Coda\Cms\Registry;
use Coda\FormKit\Form;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdminModelListPage extends AbstractModelList
{
    protected function module(): AdminModule
    {
        $pageName = $this->currentPageRouteName();
        $module = app(Registry::class)->moduleForPage($pageName);

        abort_unless($module instanceof AdminModule, 404);

        return $module;
    }

    protected function urlPrefix(): string
    {
        return $this->module()->urlPrefixValue() ?? '';
    }

    protected function getDataClass(): string
    {
        return $this->module()->dataClassName();
    }

    protected function getBaseQuery(): Builder
    {
        return $this->module()->query($this);
    }

    protected function dataFromModel(Model $model): AbstractData
    {
        $dataClass = $this->getDataClass();

        if (method_exists($dataClass, 'fromModel')) {
            return $dataClass::fromModel($model);
        }

        return parent::dataFromModel($model);
    }

    protected function getFormDefinition(): Form|array
    {
        return $this->module()->resolveForm();
    }

    protected function getTable(): Table
    {
        return $this->module()->resolveTable();
    }

    protected function getFormContextBindings(): array
    {
        return $this->module()->contextBindingsData();
    }

    protected function getEntityName(): string
    {
        return $this->module()->entityLabel();
    }

    public function detailsPageDefinition(): ?\Coda\Cms\DetailsPageDefinition
    {
        return app(Registry::class)->page($this->module()->detailPageName());
    }

    public function detailsPageUrl(Model $model): ?string
    {
        $page = $this->detailsPageDefinition();

        if (! $page instanceof \Coda\Cms\DetailsPageDefinition) {
            return null;
        }

        return app(Registry::class)->urlForRoute($page->name, ['record' => $model->getKey()]);
    }

    public function scopeValue(string $path = 'id', mixed $default = null): mixed
    {
        return $this->currentScopeValue($path, $default);
    }

    protected function getFormModalMaxWidth(): string
    {
        return $this->module()->resolveFormModalMaxWidth(default: 'max-w-sm');
    }

    protected function getDefaultOptions(): array
    {
        return [
            ...parent::getDefaultOptions(),
            ...$this->module()->optionsValue(),
        ];
    }
}
