<?php

namespace App\Livewire\Concerns;

use Flux\Flux;

trait SwitchesToVisibleTabAfterCreate
{
    public function handleFormSubmitted(array $data): void
    {
        $persistedModelId = $data['id'] ?? null;

        if (empty($data['_persisted'])) {
            $model = $this->createDataFromForm($data);
            $model->persist();
            $persistedModelId = $model->id ?? $persistedModelId;
        }

        $isNew = ! empty($data['_persisted']) ? ! empty($data['_isNew']) : empty($data['id']);
        $name = $data['name'] ?? $this->getEntityName();
        $action = $isNew ? 'created' : 'updated';

        Flux::toast(text: "{$name} {$action}", variant: 'success');

        if ($isNew && $persistedModelId !== null) {
            $listContext = $this->resolveCreatedItemListContext((int) $persistedModelId);

            if ($listContext !== null) {
                $this->selectedTab = $listContext['selectedTab'];
                $this->filters = $listContext['filters'];
                $this->setPage($listContext['page'], pageName: $this->prefixedPageName());
            }
        }

        $this->edit = null;
        $this->resetState();
        $this->refreshKey++;
        $this->emit();
    }

    /**
     * @return array{selectedTab: ?string, filters: array, page: int}|null
     */
    protected function resolveCreatedItemListContext(int $id): ?array
    {
        $snapshot = $this->captureListNavigationState();

        try {
            $tabKeys = collect($this->tabs)
                ->map(fn ($tab) => $tab->key)
                ->prepend($snapshot['selectedTab'])
                ->filter(fn ($key) => $key !== null)
                ->unique()
                ->values();

            if ($tabKeys->isEmpty()) {
                $tabKeys = collect([null]);
            }

            foreach ($tabKeys as $tabKey) {
                $this->selectedTab = $tabKey;
                $this->filters = $snapshot['filters'];
                $this->resetState();

                $this->filters = $this->filtersAllowedForSelectedTab($snapshot['filters']);

                $page = $this->resolvePageForItem($id);

                if ($page !== null) {
                    return [
                        'selectedTab' => $this->selectedTab,
                        'filters' => $this->filters,
                        'page' => $page,
                    ];
                }
            }

            return null;
        } finally {
            $this->restoreListNavigationState($snapshot);
        }
    }

    protected function filtersAllowedForSelectedTab(array $filters): array
    {
        $allowedFilterNames = collect($this->resolveTable()->getFilters())
            ->map(fn ($filter) => $filter->getName())
            ->all();

        return array_intersect_key($filters, array_flip($allowedFilterNames));
    }

    /**
     * @return array{selectedTab: ?string, filters: array, page: int}
     */
    protected function captureListNavigationState(): array
    {
        return [
            'selectedTab' => $this->selectedTab,
            'filters' => $this->filters,
            'page' => $this->getPage($this->prefixedPageName()),
        ];
    }

    /**
     * @param  array{selectedTab: ?string, filters: array, page: int}  $snapshot
     */
    protected function restoreListNavigationState(array $snapshot): void
    {
        $this->selectedTab = $snapshot['selectedTab'];
        $this->filters = $snapshot['filters'];
        $this->setPage($snapshot['page'], pageName: $this->prefixedPageName());
        $this->resetState();
    }
}
