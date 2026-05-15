<?php

namespace Coda\Cms\Livewire;

use Coda\Cms\Contracts\HasDetailsLayout;
use Coda\Cms\DetailsPageDefinition;
use Coda\Cms\Livewire\Concerns\InteractsWithFormData;
use Coda\Cms\Livewire\Concerns\InteractsWithMediaUploads;
use Coda\Cms\Models\Contracts\PersistsWithMedia;
use Coda\Cms\Registry;
use Coda\Cms\Layout\Fieldset as LayoutFieldset;
use Coda\Cms\Layout\Layout;
use Coda\Cms\Layout\Tab;
use Coda\Cms\Layout\Tabs;
use Coda\Cms\Layout\Grid;
use Coda\Cms\Layout\Column;
use Coda\Cms\Layout\Section;
use Coda\FormKit\Form;
use Coda\FormKit\FormFieldset;
use Coda\FormKit\FormFieldsetGroup;
use Flux\Flux;
use App\Support\TopicFieldDebugLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\HasMedia;

abstract class AbstractModelDetailsPage extends Component
{
    use InteractsWithFormData;
    use InteractsWithMediaUploads;
    use WithFileUploads;

    public string $pageName;

    public int $record;

    #[Url(as: 'tab', except: '', history: true)]
    public string $activeTab = '';

    public array $data = [];

    public function mount(int $record): void
    {
        $this->pageName = (string) request()->route()?->getName();
        $this->record = $record;
        $this->loadRecord();
    }

    #[Computed]
    public function pageDefinition(): DetailsPageDefinition
    {
        $page = app(Registry::class)->page($this->pageName);

        abort_unless($page instanceof DetailsPageDefinition, 404);

        return $page;
    }

    #[Computed]
    public function listComponentClass(): string
    {
        $listComponent = $this->pageDefinition->listComponent;

        abort_unless(
            is_string($listComponent) && is_a($listComponent, AbstractModelList::class, true),
            404,
        );

        return $listComponent;
    }

    protected function listComponent(): AbstractModelList
    {
        /** @var AbstractModelList $component */
        $component = app($this->listComponentClass);

        return $component;
    }

    #[Computed]
    public function backRouteName(): string
    {
        $routeName = app(Registry::class)
            ->pages()
            ->first(fn ($page) => ! $page instanceof DetailsPageDefinition && $page->component === $this->listComponentClass)
            ?->name;

        abort_unless(is_string($routeName) && $routeName !== '', 404);

        return $routeName;
    }

    protected function dataClass(): string
    {
        return $this->listComponent()->modelListDataClass();
    }

    #[Computed]
    public function formConfig(): Form
    {
        $dataClass = $this->dataClass();

        if (method_exists($dataClass, 'getForm')) {
            $definition = $dataClass::getForm();

            return $definition instanceof Form ? $definition : Form::fields($definition);
        }

        if (method_exists($dataClass, 'getFields')) {
            return Form::fields($dataClass::getFields());
        }

        return Form::fields([]);
    }

    #[Computed]
    public function fieldsets(): array
    {
        return $this->formConfig->resolveFieldsets($this->data);
    }

    #[Computed]
    public function detailsLayout(): ?Layout
    {
        $dataClass = $this->dataClass();

        if (! is_subclass_of($dataClass, HasDetailsLayout::class)) {
            return null;
        }

        $layout = $dataClass::getDetailsLayout();
        $fieldsetsByName = $this->detailsFieldsetsByName();

        $this->assertLayoutReferencesExist($layout, $fieldsetsByName);

        return $layout;
    }

    #[Computed]
    public function detailsFieldsetsByName(): array
    {
        return $this->flattenFieldsetsByName($this->fieldsets);
    }

    #[Computed]
    public function detailsRootTabs(): ?Tabs
    {
        if ($this->detailsLayout === null) {
            return null;
        }

        if (count($this->detailsLayout->schema) !== 1) {
            return null;
        }

        $rootNode = $this->detailsLayout->schema[0];

        return $rootNode instanceof Tabs ? $rootNode : null;
    }

    #[Computed]
    public function entityName(): string
    {
        return $this->listComponent()->modelListEntityName();
    }

    #[Computed]
    public function pageTitle(): string
    {
        $field = $this->listComponent()->detailsTitleField();
        $value = $field ? data_get($this->data, $field) : null;

        return filled($value) ? (string) $value : $this->entityName;
    }

    public function save(): void
    {
        TopicFieldDebugLogger::log('details.save.start', [
            'component' => static::class,
            'record' => $this->record,
            'interestTopics' => data_get($this->data, 'interestTopics'),
            'offerTopics' => data_get($this->data, 'offerTopics'),
            'topicDebug' => data_get($this->data, '__topicFieldDebug'),
        ]);

        $rules = array_merge(
            $this->buildValidationRulesFromFieldsets(),
            $this->buildMediaValidationRules()
        );

        $this->validate($rules, [
            'required' => 'This field is required.',
        ], $this->buildValidationAttributesFromFieldsets());

        $this->data = $this->castEmptyStringsToNull($this->data);
        unset($this->data['__topicFieldDebug']);

        $dataClass = $this->dataClass();
        $hasMedia = $this->hasFileUploadFields() && is_subclass_of($dataClass, PersistsWithMedia::class);

        $instance = $dataClass::from($this->data);
        $persisted = $instance->persist();
        $model = $persisted instanceof Model
            ? $persisted
            : $this->resolveModel((int) ($instance->id ?? $this->record));

        TopicFieldDebugLogger::log('details.save.persisted', [
            'component' => static::class,
            'record' => $model?->getKey(),
            'interestTopics' => method_exists($model, 'interestTags')
                ? $model->interestTags()->get()->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'score' => (float) ($tag->pivot?->score ?? 0),
                    'context' => $tag->pivot?->context,
                ])->all()
                : null,
            'offerTopics' => method_exists($model, 'offerTags')
                ? $model->offerTags()->get()->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'score' => (float) ($tag->pivot?->score ?? 0),
                    'context' => $tag->pivot?->context,
                ])->all()
                : null,
        ]);

        if ($hasMedia && $model instanceof HasMedia) {
            $this->persistAllMedia($model);
        }

        $this->record = (int) ($instance->id ?? $this->record);
        $this->loadRecord();

        Flux::toast(text: "{$this->entityName} updated", variant: 'success');
    }

    public function back(): void
    {
        $this->js('window.history.back()');
    }

    public function initializeTabState(string $default): void
    {
        $validTabs = collect($this->detailsRootTabs?->tabs ?? [])
            ->map(fn ($tab) => $tab->name)
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->values()
            ->all();

        if ($this->activeTab === '' || ($validTabs !== [] && ! in_array($this->activeTab, $validTabs, true))) {
            $this->activeTab = $default;
        }
    }

    protected function loadRecord(): void
    {
        $model = $this->resolveModel($this->record);

        abort_unless($model !== null, 404);

        $this->clearAllMediaState();
        unset($this->formConfig, $this->fieldsets, $this->detailsLayout, $this->detailsFieldsetsByName);

        $defaults = $this->buildDefaultsFromFieldsets();
        $data = $this->listComponent()->modelListDataFromModel($model);

        $this->data = array_replace_recursive($defaults, $data);

        $this->ensureRelationshipItemsHaveKeys();
        $this->loadExistingMediaFromModel($model);
    }

    protected function resolveModel(int $id): ?Model
    {
        return $this->listComponent()->modelListResolveModel($id);
    }

    protected function loadExistingMediaFromModel(Model $model): void
    {
        if (! $this->hasFileUploadFields() || ! $model instanceof HasMedia) {
            return;
        }

        $this->loadAllExistingMedia($model);
    }

    protected function castEmptyStringsToNull(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->castEmptyStringsToNull($value);
            } elseif ($value === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }

    public function render(): View
    {
        return view('cms::model-details-page');
    }

    protected function flattenFieldsetsByName(array $items): array
    {
        $fieldsets = [];

        foreach ($items as $item) {
            if ($item instanceof FormFieldset) {
                $fieldsets[$item->name] = $item;

                continue;
            }

            if ($item instanceof FormFieldsetGroup) {
                $fieldsets = array_replace(
                    $fieldsets,
                    $this->flattenFieldsetsByName($item->fieldsets),
                );
            }
        }

        return $fieldsets;
    }

    protected function assertLayoutReferencesExist(Layout $layout, array $fieldsetsByName): void
    {
        foreach ($layout->schema as $node) {
            $this->assertLayoutNodeReferencesExist($node, $fieldsetsByName);
        }
    }

    protected function assertLayoutNodeReferencesExist(mixed $node, array $fieldsetsByName): void
    {
        if ($node instanceof LayoutFieldset) {
            if (! array_key_exists($node->name, $fieldsetsByName)) {
                throw new \InvalidArgumentException("Unknown fieldset [{$node->name}] referenced in details layout.");
            }

            return;
        }

        if ($node instanceof Tabs) {
            foreach ($node->tabs as $tab) {
                $this->assertLayoutNodeReferencesExist($tab, $fieldsetsByName);
            }

            return;
        }

        if ($node instanceof Tab || $node instanceof Grid || $node instanceof Column || $node instanceof Section) {
            foreach ($node->schema as $child) {
                $this->assertLayoutNodeReferencesExist($child, $fieldsetsByName);
            }
        }
    }
}
