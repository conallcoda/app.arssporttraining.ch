<?php

namespace App\Livewire\Concerns;

trait ManagesDatabaseList
{
    public bool $showAddForm = false;

    public function toggleAddForm(): void
    {
        $this->showAddForm = ! $this->showAddForm;
    }

    public function cancelAdd(): void
    {
        $this->showAddForm = false;
        $this->resetAddForm();
    }

    protected function getNextId(): int
    {
        $items = $this->getItems();
        if (empty($items)) {
            return 1;
        }

        $maxId = max(array_map(fn ($item) => $item->id, $items));

        return $maxId + 1;
    }

    abstract protected function getItems(): array;

    abstract protected function resetAddForm(): void;
}
