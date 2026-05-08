<?php

namespace Coda\Cms\Livewire;

use Coda\Cms\Data\GroupedTreeItemData;
use Coda\FormKit\Action;
use Coda\FormKit\ActionPlacement;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

abstract class AbstractGroupedModelTree extends Component
{
    public array $options = [];

    public ?string $confirmingKey = null;

    public ?string $confirmingAction = null;

    public ?string $confirmDescription = null;

    public int $refreshKey = 0;

    abstract protected function buildTreeItems(): array;

    protected function getActions(): array
    {
        return [];
    }

    protected function getDefaultOptions(): array
    {
        return [];
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

    protected function getModalContextData(string $formDataClass): array
    {
        return [];
    }

    protected function getModalExcludedFields(string $formDataClass): array
    {
        return [];
    }

    protected function getFormModalMaxWidth(): string
    {
        return 'max-w-sm';
    }

    public function mount(): void
    {
        $this->options = array_merge($this->getDefaultOptions(), $this->options);
    }

    public function render(): View
    {
        return view('cms::grouped-model-tree', [
            'entityName' => $this->getEntityName(),
            'entitySlug' => $this->getEntitySlug(),
            'options' => $this->options,
        ]);
    }

    #[Computed(persist: false)]
    public function actions(): array
    {
        return $this->getActions();
    }

    #[Computed(persist: false)]
    public function treeItems(): array
    {
        return $this->buildTreeItems();
    }

    #[Computed(persist: false)]
    public function flatTreeItems(): array
    {
        return $this->flattenTree($this->treeItems, []);
    }

    protected function flattenTree(array $items, array $ancestorKeys): array
    {
        $result = [];
        $count = count($items);

        foreach ($items as $index => $item) {
            $item->ancestorKeys = $ancestorKeys;
            $item->isFirstSibling = ($index === 0);
            $item->isLastSibling = ($index === $count - 1);
            $result[] = $item;

            if (! empty($item->children)) {
                $childAncestorKeys = array_merge($ancestorKeys, [$item->key]);
                $result = array_merge($result, $this->flattenTree($item->children, $childAncestorKeys));
            }
        }

        return $result;
    }

    protected function refreshTree(): void
    {
        unset($this->treeItems, $this->flatTreeItems);
        $this->refreshKey++;
        $this->emit();
    }

    protected function findItem(string $key): ?GroupedTreeItemData
    {
        foreach ($this->flatTreeItems as $item) {
            if ($item->key === $key) {
                return $item;
            }
        }

        return null;
    }

    #[Computed]
    public function headerActions(): array
    {
        return collect($this->actions)
            ->filter(fn (Action $action) => $action->placement === ActionPlacement::Header)
            ->values()
            ->all();
    }

    #[Computed]
    public function rowActions(): array
    {
        return collect($this->actions)
            ->filter(fn (Action $action) => $action->placement === ActionPlacement::Row)
            ->values()
            ->all();
    }

    #[Computed]
    public function rowMenuActions(): array
    {
        return collect($this->actions)
            ->filter(fn (Action $action) => $action->placement === ActionPlacement::RowMenu)
            ->values()
            ->all();
    }

    #[Computed]
    public function formModals(): array
    {
        $modals = [];
        $seenFormClasses = [];

        foreach ($this->actions as $action) {
            if (! $action->isFormModal() || $action->formDataClass === null) {
                continue;
            }

            if (in_array($action->formDataClass, $seenFormClasses, true)) {
                continue;
            }

            $seenFormClasses[] = $action->formDataClass;

            $modals[] = [
                'name' => $this->getModalNameForAction($action),
                'title' => $action->modalTitle,
                'formDataClass' => $action->formDataClass,
                'submitLabel' => $action->submitLabel ?? 'Save',
                'formComponent' => $action->formComponent,
                'contextData' => $this->getModalContextData($action->formDataClass),
                'excludeFields' => $this->getModalExcludedFields($action->formDataClass),
                'maxWidth' => $this->getFormModalMaxWidth(),
            ];
        }

        return $modals;
    }

    #[Computed]
    public function confirmModals(): array
    {
        return collect($this->actions)
            ->filter(fn (Action $action) => $action->isConfirm())
            ->values()
            ->all();
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

        foreach ($this->actions as $action) {
            if (! $action->isFormModal()) {
                continue;
            }

            $listeners[$this->getModalNameForAction($action).'.submitted'] = 'handleFormActionSubmitted';
        }

        return $listeners;
    }

    public function handleFormActionSubmitted(array $data): void
    {
        $actionName = $data['_groupedTreeAction'] ?? null;
        $action = collect($this->actions)->firstWhere('name', $actionName);

        if (! $action || ! $action->isFormModal()) {
            return;
        }

        unset($data['_groupedTreeAction']);

        $handler = $action->getHandler();
        $this->$handler($data);
    }

    public function confirmAction(string $actionName, string $key): void
    {
        $action = collect($this->actions)->firstWhere('name', $actionName);
        $item = $this->findItem($key);

        if (! $action || ! $action->isConfirm() || ! $item || ! $action->isVisible($item)) {
            return;
        }

        $this->confirmingKey = $key;
        $this->confirmingAction = $actionName;
        $this->confirmDescription = $action->confirmDescription;

        Flux::modal($action->resolveModalName($this->getEntitySlug()))->show();
    }

    public function executeConfirmedAction(): void
    {
        if ($this->confirmingKey === null || $this->confirmingAction === null) {
            return;
        }

        $action = collect($this->actions)->firstWhere('name', $this->confirmingAction);

        if (! $action) {
            return;
        }

        $key = $this->confirmingKey;

        $this->confirmingKey = null;
        $this->confirmingAction = null;
        $this->confirmDescription = null;

        Flux::modal($action->resolveModalName($this->getEntitySlug()))->close();

        $handler = $action->getHandler();
        $this->$handler($key);
    }

    public function openActionModal(string $actionName, string $key): void
    {
        $action = collect($this->actions)->firstWhere('name', $actionName);
        $item = $this->findItem($key);

        if (! $action || ! $action->isFormModal() || ! $item || ! $action->isVisible($item)) {
            return;
        }

        $data = $action->passesItemData ? $item->formData : [];

        if ($action->prepareData) {
            $data = ($action->prepareData)($item, $data);
        }

        $data['_groupedTreeAction'] = $action->name;

        $this->dispatch(
            'open-'.$this->getModalNameForAction($action),
            data: $data,
            title: $action->modalTitle
        );
    }

    protected function emit(): void {}
}
