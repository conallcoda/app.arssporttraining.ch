<?php

namespace Coda\Cms;

use Closure;
use Coda\Cms\Layouts\DetailsLayout;
use Coda\Cms\Layouts\DetailsTab;
use Coda\Cms\Layouts\FacetLayout;
use Coda\Cms\Layouts\FormLayout;
use Coda\Cms\Layouts\TableLayout;
use Coda\Cms\Layouts\TreeLayout;
use Coda\Cms\Livewire\AdminModelDetailsPage;
use Coda\Cms\Livewire\AdminModelListPage;
use Coda\Cms\Schema\CmsSchemaPresenter;
use Coda\SchemaKit\DetailsTabDefinition;
use Coda\SchemaKit\DetailsViewDefinition;
use Coda\SchemaKit\Entity;
use Coda\SchemaKit\EntityDefinition;
use Coda\SchemaKit\FacetDetailsDefinition;
use Coda\SchemaKit\FacetDefinition;
use Coda\SchemaKit\FacetFormDefinition;
use Coda\SchemaKit\ScopeDefinition;
use Coda\SchemaKit\SchemaRegistry;
use Coda\SchemaKit\TableViewDefinition;
use Coda\SchemaKit\View;
use Coda\FormKit\Form;
use Coda\Cms\Layout\Layout;
use Coda\Cms\Display\Table;
use Coda\Cms\Registry;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AdminModule extends Module
{
    private string $moduleName;

    private string $moduleLabel;

    private string $moduleIcon = '';

    private string $moduleRoute = '';

    private string $moduleHeading = '';

    private array $moduleMiddleware = ['auth'];

    private ?string $moduleDataClass = null;

    private ?string $moduleRecordModel = null;

    private ?string $moduleUrlPrefix = null;

    private ?string $moduleDetailsComponent = null;

    private ?Closure $queryUsing = null;

    private array $contextBindings = [];

    /** @var array<int, string> */
    private array $activeScopes = [];

    private ?FormLayout $formLayout = null;

    private ?DetailsLayout $detailsLayout = null;

    private ?TableLayout $tableLayout = null;

    private ?TreeLayout $treeLayout = null;

    private ?EntityDefinition $entityDefinition = null;

    protected function __construct(string $name)
    {
        $this->moduleName = $name;
        $this->moduleLabel = str($name)->replace(['.', '-', '_'], ' ')->headline()->toString();
        $this->moduleHeading = $this->moduleLabel;
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function name(): string
    {
        return $this->moduleName;
    }

    public function label(): string
    {
        return $this->moduleLabel;
    }

    public function icon(): string
    {
        return $this->moduleIcon;
    }

    public function route(string $route): static
    {
        $this->moduleRoute = $route;

        return $this;
    }

    public function heading(string $heading): static
    {
        $this->moduleHeading = $heading;

        return $this;
    }

    public function middleware(array $middleware): static
    {
        $this->moduleMiddleware = array_values($middleware);

        return $this;
    }

    public function labelAs(string $label): static
    {
        $this->moduleLabel = $label;
        $this->moduleHeading = $this->moduleHeading === '' ? $label : $this->moduleHeading;

        return $this;
    }

    public function iconAs(string $icon): static
    {
        $this->moduleIcon = $icon;

        return $this;
    }

    public function entity(string $name): static
    {
        $this->entityDefinition = Entity::make($name);

        return $this;
    }

    public function useEntityDefinition(EntityDefinition $entityDefinition): static
    {
        $this->entityDefinition = $entityDefinition;

        return $this;
    }

    public function schema(EntityDefinition $entityDefinition): static
    {
        return $this->useEntityDefinition($entityDefinition);
    }

    public function model(string $modelClass): static
    {
        $this->requireEntityDefinition()->model($modelClass);

        return $this;
    }

    public function payloadUsing(Closure $closure): static
    {
        $this->requireEntityDefinition()->toPayloadUsing($closure);

        return $this;
    }

    public function resolveUsing(Closure $closure): static
    {
        $this->requireEntityDefinition()->resolveModelUsing($closure);

        return $this;
    }

    public function persistUsing(Closure $closure): static
    {
        $this->requireEntityDefinition()->persistUsing($closure);

        return $this;
    }

    public function identity(mixed $configure): static
    {
        $this->requireEntityDefinition()->identity($configure);

        return $this;
    }

    public function facet(FacetDefinition|string $name, string|callable|null $binding = null, ?callable $configure = null, ?string $as = null): static
    {
        $this->requireEntityDefinition()->facet($name, $binding, $configure, $as);

        return $this;
    }

    public function facetLayout(FacetLayout $layout): static
    {
        $this->applyFormFacetLayout($layout);

        return $this;
    }

    /**
     * @param  array<int, FacetLayout>  $layouts
     */
    public function facetLayouts(array $layouts): static
    {
        foreach ($layouts as $layout) {
            $this->facetLayout($layout);
        }

        return $this;
    }

    public function facets(array $facets): static
    {
        $this->requireEntityDefinition()->facets($facets);

        return $this;
    }

    public function importFacet(string $source, string $binding, ?string $as = null): FacetDefinition
    {
        return $this->requireEntityDefinition()->importFacet($source, $binding, $as);
    }

    public function dataClass(string $dataClass): static
    {
        $this->moduleDataClass = $dataClass;

        return $this;
    }

    public function urlPrefix(?string $urlPrefix): static
    {
        $this->moduleUrlPrefix = $urlPrefix;

        return $this;
    }

    public function recordModel(?string $recordModel): static
    {
        $this->moduleRecordModel = $recordModel;

        return $this;
    }

    public function detailsComponent(string $component): static
    {
        $this->moduleDetailsComponent = $component;

        return $this;
    }

    public function queryUsing(Closure $queryUsing): static
    {
        $this->queryUsing = $queryUsing;

        return $this;
    }

    public function contextBindings(array $contextBindings): static
    {
        $this->contextBindings = $contextBindings;

        return $this;
    }

    public function scopedTo(string $scope): static
    {
        if (! in_array($scope, $this->activeScopes, true)) {
            $this->activeScopes[] = $scope;
        }

        return $this;
    }

    public function form(FormLayout $layout): static
    {
        $this->formLayout = $layout;

        foreach ($layout->getFacetLayouts() as $facetLayout) {
            $this->applyFormFacetLayout($facetLayout);
        }

        $this->requireEntityDefinition()->view(
            View::make('cms_edit')
                ->facets($layout->getFacets())
                ->formModalWidth($layout->getModalWidth())
                ->formTabs($layout->getTabs())
        );

        return $this;
    }

    public function details(DetailsLayout $layout): static
    {
        $this->detailsLayout = $layout;

        $details = DetailsViewDefinition::make();

        foreach ($layout->getTabs() as $tab) {
            $details->tab(
                DetailsTabDefinition::make($tab->title())
                    ->left($tab->getLeft())
                    ->right($tab->getRight())
                    ->infoBox($tab->getInfoBox())
            );
        }

        $this->requireEntityDefinition()->view(
            View::make('cms_details')
                ->facets($layout->getFacets())
                ->details($details)
        );

        return $this;
    }

    public function table(TableLayout $layout): static
    {
        $this->tableLayout = $layout;

        $this->requireEntityDefinition()->view(
            View::make('cms_list')
                ->facets($layout->getFacets())
                ->table(
                    TableViewDefinition::make()
                        ->columns($layout->getColumns())
                        ->sortable($layout->getSortable())
                        ->filters($layout->getFilters())
                        ->defaultSort(
                            $layout->getDefaultSort()['field'] ?? 'id',
                            $layout->getDefaultSort()['direction'] ?? 'asc',
                        )
                )
        );

        return $this;
    }

    public function tree(TreeLayout $layout): static
    {
        $this->treeLayout = $layout;

        return $this;
    }

    public function pages(): array
    {
        return [
            PageDefinition::make($this->moduleName)
                ->route($this->moduleRoute)
                ->heading($this->moduleHeading)
                ->component(AdminModelListPage::class)
                ->middleware($this->moduleMiddleware),
        ];
    }

    public function detailPages(): array
    {
        if (! array_key_exists('cms_details', $this->requireEntityDefinition()->getViews())) {
            return [];
        }

        $page = $this->detailsPage(
            name: $this->detailPageName(),
            route: trim($this->moduleRoute, '/').'/{record}',
            listComponent: AdminModelListPage::class,
            component: $this->moduleDetailsComponent ?? AdminModelDetailsPage::class,
        )->middleware($this->moduleMiddleware);

        if ($this->moduleRecordModel !== null) {
            $page->bindCrumb('record', $this->moduleRecordModel);
        }

        return [$page];
    }

    public function entityDefinition(): EntityDefinition
    {
        return $this->requireEntityDefinition();
    }

    public function entityName(): string
    {
        return $this->requireEntityDefinition()->name();
    }

    public function dataClassName(): string
    {
        return $this->moduleDataClass ?? throw new \InvalidArgumentException("Admin module [{$this->moduleName}] is missing a dataClass.");
    }

    public function query(object $component): Builder
    {
        if (! $this->queryUsing instanceof Closure) {
            throw new \InvalidArgumentException("Admin module [{$this->moduleName}] is missing a queryUsing closure.");
        }

        $query = ($this->queryUsing)($component);

        foreach ($this->activeScopes as $scopeKey) {
            $query = $this->applyScopeToQuery($query, $scopeKey, $component);
        }

        return $query;
    }

    public function contextBindingsData(): array
    {
        return [
            ...$this->scopeContextBindings(),
            ...$this->contextBindings,
        ];
    }

    public function urlPrefixValue(): ?string
    {
        return $this->moduleUrlPrefix;
    }

    public function detailPageName(): string
    {
        return $this->moduleName.'.details';
    }

    public function entityLabel(): string
    {
        return $this->schemaPresenter()->entityLabel($this->entityName());
    }

    public function pluralEntityLabel(): string
    {
        return $this->schemaPresenter()->pluralEntityLabel($this->entityName());
    }

    public function resolveForm(string $viewName = 'cms_edit'): Form
    {
        return $this->schemaPresenter()->form($this->entityName(), $viewName);
    }

    public function resolveDetails(string $viewName = 'cms_details'): Layout
    {
        return $this->schemaPresenter()->details($this->entityName(), $viewName);
    }

    public function resolveTable(string $viewName = 'cms_list'): Table
    {
        return $this->schemaPresenter()->table($this->entityName(), $viewName);
    }

    public function resolveFormModalMaxWidth(string $viewName = 'cms_edit', string $default = 'max-w-sm'): string
    {
        return $this->schemaPresenter()->formModalMaxWidth($this->entityName(), $viewName, $default);
    }

    private function requireEntityDefinition(): EntityDefinition
    {
        return $this->entityDefinition
            ?? throw new \InvalidArgumentException("Admin module [{$this->moduleName}] is missing an entity definition. Call entity(...).");
    }

    private function schemaPresenter(): CmsSchemaPresenter
    {
        /** @var SchemaRegistry $registry */
        $registry = clone app(SchemaRegistry::class);
        $registry->register($this->requireEntityDefinition());

        return CmsSchemaPresenter::make($registry);
    }

    private function scopeContextBindings(): array
    {
        $bindings = [];

        foreach ($this->activeScopes as $scopeKey) {
            $scope = $this->requireEntityDefinition()->requireScope($scopeKey);
            $field = $scope->getField();

            if (! is_string($field) || $field === '') {
                continue;
            }

            $bindings[$field] = fn (mixed $context = null) => $this->currentScopeValueFor($scope);
        }

        return $bindings;
    }

    private function applyScopeToQuery(Builder $query, string $scopeKey, object $component): Builder
    {
        $scope = $this->requireEntityDefinition()->requireScope($scopeKey);
        $value = $this->currentScopeValueFor($scope);

        if ($value === null || $value === '') {
            return $query;
        }

        $queryUsing = $scope->getQueryUsing();

        if ($queryUsing instanceof Closure) {
            $queryUsing($query, $value, $component, $scope, $this);

            return $query;
        }

        $attribute = $scope->getAttribute();

        if (is_string($attribute) && $attribute !== '') {
            $query->where($attribute, $value);
        }

        return $query;
    }

    private function currentScopeValueFor(ScopeDefinition $scope): mixed
    {
        return app(Registry::class)->currentContextValue($scope->getContextPath());
    }

    private function applyFormFacetLayout(FacetLayout $layout): void
    {
        /** @var FacetDefinition $facet */
        $facet = $this->requireEntityDefinition()->facet($layout->name());
        $fieldset = $layout->getFieldset() ?? $facet->name();

        $facet->form(function (FacetFormDefinition $form) use ($layout, $fieldset): void {
            if ($layout->getLabel() !== null) {
                $form->label($layout->getLabel());
            }

            if ($layout->getFields() !== null) {
                $form->fields($layout->getFields());
            }

            if ($layout->getView() !== null) {
                $form->view($layout->getView());
            }

            if ($layout->getViewData() !== []) {
                $form->viewData($layout->getViewData());
            }

            if ($layout->getTab() !== null) {
                $form->tab($layout->getTab());
            }

            if ($layout->getLayout() !== null) {
                $form->layout($layout->getLayout());
            }

            $form->fieldset($fieldset);
        });

        $facet->details(
            fn (FacetDetailsDefinition $details) => $details->fieldset($fieldset)
        );
    }
}
