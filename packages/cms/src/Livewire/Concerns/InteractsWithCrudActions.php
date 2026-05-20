<?php

namespace Coda\Cms\Livewire\Concerns;

use Coda\FormKit\Action;
use Coda\FormKit\ActionPlacement;
use Flux\Flux;
use Livewire\Attributes\Computed;

trait InteractsWithCrudActions
{
    abstract protected function getDataClass(): string;

    abstract protected function getBaseQuery();

    abstract protected function getEntitySlug(): string;

    abstract protected function getEntityName(): string;

    protected function getActions(): array
    {
        $useTreeCreateButton = method_exists($this, 'canCreateRoots') && $this->canCreateRoots();

        return array_filter([
            $useTreeCreateButton ? null : $this->getAddAction(),
            $this->getEditAction(),
            $this->getDeleteAction(),
            ...$this->getSortActions(),
            ...$this->getExtraActions(),
        ]);
    }

    protected function getAddAction(): ?Action
    {
        if (! $this->option('showAddButton', true)) {
            return null;
        }

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
        return [];
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
            $modal = [
                'name' => $action->resolveModalName($this->getEntitySlug()),
                'title' => $action->modalTitle,
                'formDataClass' => $action->formDataClass,
                'submitLabel' => $action->submitLabel ?? 'Save',
                'formComponent' => $action->formComponent,
                'contextData' => $action->formDataClass === $this->getDataClass() ? $this->getFormContextData() : [],
                'excludeFields' => $action->formDataClass === $this->getDataClass() ? $this->getFormExcludedFields() : [],
            ];

            if (method_exists($this, 'getFormModalMaxWidth')) {
                $modal['maxWidth'] = $this->resolveFormModalMaxWidth($action->formDataClass);
            }

            $modals[] = $modal;
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

        $listeners["{$this->editModalName}.closed"] = 'handleModalClosed';

        if (method_exists($this, 'canCreateRoots') && $this->canCreateRoots()) {
            $listeners[$this->createModalName().'.submitted'] = 'handleTreeCreateSubmitted';
        }

        return $listeners;
    }

    public function handleModalClosed(): void
    {
        $this->edit = null;
    }

    protected function resolveFormModalMaxWidth(?string $formDataClass, string $default = 'max-w-sm'): string
    {
        if (is_string($formDataClass) && method_exists($formDataClass, 'getFormModalMaxWidth')) {
            $width = $formDataClass::getFormModalMaxWidth();

            if (is_string($width) && $width !== '') {
                return $width;
            }
        }

        if (method_exists($this, 'getFormModalMaxWidth')) {
            $width = $this->getFormModalMaxWidth();

            if (is_string($width) && $width !== '') {
                return $width;
            }
        }

        return $default;
    }

    public function confirmAction(string $actionName, int $id): void
    {
        $action = collect($this->actions)->firstWhere('name', $actionName);
        if (! $action || ! $action->isConfirm()) {
            return;
        }

        $this->confirmingId = $id;
        $this->confirmingAction = $actionName;
        $this->confirmDescription = $action->confirmDescription;
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
        $this->confirmDescription = null;

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
        $model = $this->getBaseQuery()->findOrFail($id);
        $data = $action->passesItemData
            ? $this->dataFromModel($model)->toArray()
            : ['id' => $id];

        if ($action->prepareData) {
            $data = ($action->prepareData)($model, $data);
        }

        $this->dispatch("open-{$modalName}", data: $data, title: $action->modalTitle);
    }

    public function openHeaderActionModal(string $actionName): void
    {
        $action = collect($this->actions)->firstWhere('name', $actionName);
        if (! $action || ! $action->isFormModal()) {
            return;
        }

        $modalName = $this->getModalNameForAction($action);

        $this->dispatch("open-{$modalName}", data: [], title: $action->modalTitle);
    }
}
