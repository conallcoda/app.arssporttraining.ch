<?php

namespace Coda\Cms\Livewire;

use Coda\Cms\Data\GroupedTreeItemData;
use Coda\FormKit\Action;
use Flux\Flux;
use Livewire\Attributes\Computed;

abstract class AbstractGroupedModelTree extends AbstractTree
{
    abstract protected function buildTreeItems(): array;

    protected function getActions(): array
    {
        return [];
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

        if ($this->canCreateRoots()) {
            $listeners[$this->createModalName().'.submitted'] = 'handleTreeCreateSubmitted';
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

    public function openHeaderActionModal(string $actionName): void
    {
        $action = collect($this->actions)->firstWhere('name', $actionName);

        if (! $action || ! $action->isFormModal()) {
            return;
        }

        $data = ['_groupedTreeAction' => $action->name];

        $this->dispatch(
            'open-'.$this->getModalNameForAction($action),
            data: $data,
            title: $action->modalTitle
        );
    }

    public function openActionModal(string $actionName, string $key): void
    {
        $action = collect($this->actions)->firstWhere('name', $actionName);
        $item = $this->findItem($key);

        if (! $action || ! $action->isFormModal() || ! $item || ! $action->isVisible($item)) {
            return;
        }

        $data = $action->passesItemData ? $item->formData : [];
        $data['_groupedTreeAction'] = $action->name;

        if ($action->prepareData) {
            $data = ($action->prepareData)($item, $data);
        }

        $this->dispatch(
            'open-'.$this->getModalNameForAction($action),
            data: $data,
            title: $action->modalTitle
        );
    }

    protected function emit(): void {}
}
