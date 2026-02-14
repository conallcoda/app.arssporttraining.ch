<?php

namespace App\Cms\Livewire;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Action;
use App\Cms\Form\ActionPlacement;
use App\Cms\Form\Field;
use App\Cms\Form\Fields\Relationship;
use App\Cms\Form\Form;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

abstract class AbstractModelTree extends Component
{
    use Concerns\InteractsWithFormData;

    public array $data = [];

    public ?int $confirmingId = null;

    public ?string $confirmingAction = null;

    public int $refreshKey = 0;

    public ?string $confirmDescription = null;

    abstract protected function getDataClass(): string;

    abstract protected function getBaseQuery(): Builder;

    abstract protected function getRootsQuery(): Builder;

    protected function dataFromModel(Model $model): AbstractData
    {
        return $this->getDataClass()::from($model);
    }

    protected function getActions(): array
    {
        return array_filter([
            $this->getAddAction(),
            $this->getEditAction(),
            $this->getDeleteAction(),
            ...$this->getSortActions(),
            ...$this->getExtraActions(),
        ]);
    }

    protected function getAddAction(): Action
    {
        return Action::make('add', 'Add '.$this->getEntityName())
            ->header()
            ->icon('plus')
            ->variant('primary')
            ->formModal($this->getDataClass(), 'Add '.$this->getEntityName())
            ->handler('handleFormSubmitted');
    }

    protected function getEditAction(): Action
    {
        return Action::make('edit', 'Edit')
            ->row()
            ->icon('pencil')
            ->formModal($this->getDataClass(), 'Edit '.$this->getEntityName())
            ->passesItemData()
            ->handler('handleFormSubmitted');
    }

    protected function getDeleteAction(): Action
    {
        return Action::make('delete', 'Delete')
            ->row()
            ->icon('trash-2')
            ->confirm(
                heading: 'Delete '.$this->getEntityName().'?',
                description: "You're about to delete this ".strtolower($this->getEntityName()).".\nThis action cannot be reversed.",
                buttonLabel: 'Delete',
            )
            ->handler('removeItem');
    }

    protected function getSortActions(): array
    {
        return [
            Action::make('moveUp', '')
                ->row()
                ->icon('chevron-up')
                ->disabledWhen('first'),
            Action::make('moveDown', '')
                ->row()
                ->icon('chevron-down')
                ->disabledWhen('last'),
        ];
    }

    protected function getExtraActions(): array
    {
        return [];
    }

    #[Computed]
    public function actions(): array
    {
        return $this->getActions();
    }

    #[Computed]
    public function headerActions(): array
    {
        return collect($this->actions)
            ->filter(fn (Action $a) => $a->placement === ActionPlacement::Header)
            ->values()
            ->all();
    }

    #[Computed]
    public function rowActions(): array
    {
        return collect($this->actions)
            ->filter(fn (Action $a) => $a->placement === ActionPlacement::Row)
            ->values()
            ->all();
    }

    #[Computed]
    public function rowMenuActions(): array
    {
        return collect($this->actions)
            ->filter(fn (Action $a) => $a->placement === ActionPlacement::RowMenu)
            ->values()
            ->all();
    }

    #[Computed]
    public function formModals(): array
    {
        $modals = [];
        $seenFormClasses = [];

        foreach ($this->actions as $action) {
            if (! $action->isFormModal()) {
                continue;
            }

            if (in_array($action->formDataClass, $seenFormClasses, true)) {
                continue;
            }

            $seenFormClasses[] = $action->formDataClass;
            $modals[] = [
                'name' => $action->resolveModalName($this->getEntitySlug()),
                'title' => $action->modalTitle,
                'formDataClass' => $action->formDataClass,
                'submitLabel' => $action->submitLabel ?? 'Save',
            ];
        }

        return $modals;
    }

    #[Computed]
    public function confirmModals(): array
    {
        return collect($this->actions)
            ->filter(fn (Action $a) => $a->isConfirm())
            ->values()
            ->all();
    }

    #[Computed]
    public function editModalName(): string
    {
        foreach ($this->actions as $action) {
            if ($action->isFormModal() && $action->formDataClass === $this->getDataClass()) {
                return $action->resolveModalName($this->getEntitySlug());
            }
        }

        return 'add-'.$this->getEntitySlug();
    }

    public function getModalNameForAction(Action $action): string
    {
        if (! $action->isFormModal()) {
            return $action->resolveModalName($this->getEntitySlug());
        }

        foreach ($this->actions as $other) {
            if ($other->isFormModal() && $other->formDataClass === $action->formDataClass) {
                return $other->resolveModalName($this->getEntitySlug());
            }
        }

        return $action->resolveModalName($this->getEntitySlug());
    }

    /** @return array<string, string> */
    public function getListeners(): array
    {
        $listeners = [];

        foreach ($this->getActions() as $action) {
            if (! $action->isFormModal()) {
                continue;
            }

            $modalName = $action->resolveModalName($this->getEntitySlug());
            $key = "{$modalName}.submitted";

            if (! isset($listeners[$key])) {
                $listeners[$key] = $action->getHandler();
            }
        }

        return $listeners;
    }

    public function confirmAction(string $actionName, int $id): void
    {
        $action = collect($this->actions)->firstWhere('name', $actionName);
        if (! $action || ! $action->isConfirm()) {
            return;
        }

        $this->confirmingId = $id;
        $this->confirmingAction = $actionName;
        Flux::modal($action->resolveModalName($this->getEntitySlug()))->show();
    }

    public function executeConfirmedAction(): void
    {
        if (! $this->confirmingId || ! $this->confirmingAction) {
            return;
        }

        $action = collect($this->actions)->firstWhere('name', $this->confirmingAction);
        if (! $action) {
            return;
        }

        $handler = $action->getHandler();
        $id = $this->confirmingId;

        $this->confirmingId = null;
        $this->confirmingAction = null;

        Flux::modal($action->resolveModalName($this->getEntitySlug()))->close();

        $this->$handler($id);
    }

    public function openActionModal(string $actionName, int $id): void
    {
        $action = collect($this->actions)->firstWhere('name', $actionName);
        if (! $action || ! $action->isFormModal()) {
            return;
        }

        $modalName = $this->getModalNameForAction($action);
        $data = ['id' => $id];

        if ($action->prepareData) {
            $model = $this->getBaseQuery()->findOrFail($id);
            $data = ($action->prepareData)($model, $data);
        }

        $this->dispatch("open-{$modalName}", data: $data, title: $action->modalTitle);
    }

    #[Computed(persist: false)]
    public function treeItems(): array
    {
        $roots = $this->getRootsQuery()
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $this->eagerLoadNestedChildren($roots);

        return $roots
            ->map(fn (Model $model) => $this->dataFromModel($model))
            ->all();
    }

    protected function eagerLoadNestedChildren($items): void
    {
        $children = new \Illuminate\Database\Eloquent\Collection(
            $items->pluck('children')->flatten()->all()
        );

        if ($children->isEmpty()) {
            return;
        }

        $children->load(['children' => fn ($q) => $q->orderBy('sort_order')]);
        $this->eagerLoadNestedChildren($children);
    }

    #[Computed(persist: false)]
    public function flatTreeItems(): array
    {
        return $this->flattenTree($this->treeItems, []);
    }

    protected function flattenTree(array $items, array $ancestorIds): array
    {
        $result = [];
        $count = count($items);

        foreach ($items as $index => $item) {
            $item->ancestorIds = $ancestorIds;
            $item->isFirstSibling = ($index === 0);
            $item->isLastSibling = ($index === $count - 1);
            $result[] = $item;

            if (! empty($item->children)) {
                $childAncestorIds = array_merge($ancestorIds, [$item->id]);
                $result = array_merge($result, $this->flattenTree($item->children, $childAncestorIds));
            }
        }

        return $result;
    }

    public function handleFormSubmitted(array $data): void
    {
        $isNew = empty($data['id']);

        $dto = $this->createDataFromForm($data);
        $dto->persist();

        if ($isNew) {
            $maxSort = $this->getBaseQuery()
                ->where('parent_id', $dto->parentId ?? null)
                ->where('id', '!=', $dto->id)
                ->max('sort_order') ?? -1;

            $model = $this->getBaseQuery()->findOrFail($dto->id);
            $model->sort_order = $maxSort + 1;
            $model->save();
        }

        unset($this->treeItems, $this->flatTreeItems);
        $this->refreshKey++;
        $this->emit();
    }

    public function removeItem(int $id): void
    {
        $this->getBaseQuery()->where('id', $id)->delete();

        unset($this->treeItems, $this->flatTreeItems);
        $this->refreshKey++;
        $this->emit();
    }

    public function moveUp(int $id): void
    {
        $item = $this->getBaseQuery()->findOrFail($id);
        $currentSort = $item->sort_order;

        if ($currentSort <= 0) {
            return;
        }

        $previousSibling = $this->getBaseQuery()
            ->where('parent_id', $item->parent_id)
            ->where('sort_order', $currentSort - 1)
            ->first();

        if ($previousSibling) {
            $previousSibling->sort_order = $currentSort;
            $previousSibling->save();
        }

        $item->sort_order = $currentSort - 1;
        $item->save();

        unset($this->treeItems, $this->flatTreeItems);
        $this->refreshKey++;
        $this->emit();
    }

    public function moveDown(int $id): void
    {
        $item = $this->getBaseQuery()->findOrFail($id);
        $currentSort = $item->sort_order;
        $maxSort = $this->getBaseQuery()
            ->where('parent_id', $item->parent_id)
            ->max('sort_order');

        if ($currentSort >= $maxSort) {
            return;
        }

        $nextSibling = $this->getBaseQuery()
            ->where('parent_id', $item->parent_id)
            ->where('sort_order', $currentSort + 1)
            ->first();

        if ($nextSibling) {
            $nextSibling->sort_order = $currentSort;
            $nextSibling->save();
        }

        $item->sort_order = $currentSort + 1;
        $item->save();

        unset($this->treeItems, $this->flatTreeItems);
        $this->refreshKey++;
        $this->emit();
    }

    protected function normalizeSortOrder(?int $parentId): void
    {
        $siblings = $this->getBaseQuery()
            ->where('parent_id', $parentId)
            ->orderBy('sort_order')
            ->get();

        foreach ($siblings as $index => $sibling) {
            if ($sibling->sort_order !== $index) {
                $sibling->sort_order = $index;
                $sibling->save();
            }
        }
    }

    protected function createDataFromForm(array $formData): AbstractData
    {
        return $this->getDataClass()::from($formData);
    }

    protected function getEntitySlug(): string
    {
        return Str::of(class_basename($this))
            ->replaceLast('Tree', '')
            ->snake()
            ->slug()
            ->toString();
    }

    protected function getEntityName(): string
    {
        return Str::of(class_basename($this))
            ->replaceLast('Tree', '')
            ->headline()
            ->toString();
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

    protected function getAllFields(): array
    {
        return collect($this->fieldsets)
            ->flatMap(fn (\App\Cms\Form\FormFieldset $fs) => $fs->fields)
            ->all();
    }

    protected function getFormRelationshipsToLoad(): array
    {
        return collect($this->getAllFields())
            ->filter(fn (Field $field) => $field instanceof Relationship)
            ->map(fn (Field $field) => $field->name)
            ->values()
            ->all();
    }

    public function mount(): void
    {
        $this->data = $this->buildDefaultsFromFieldsets();
    }

    protected function emit(): void {}

    public function render(): View
    {
        return view('livewire.components.model-tree', [
            'entityName' => $this->getEntityName(),
            'entitySlug' => $this->getEntitySlug(),
        ]);
    }
}
