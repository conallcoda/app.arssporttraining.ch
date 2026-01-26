<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;

trait InteractsWithParentView
{
    protected function notifyParent(string $event, array $data = []): void
    {
        $this->dispatch($event, ...$data);
    }

    protected function notifyDataChanged(string $key, mixed $value): void
    {
        $this->dispatch('data-changed', key: $key, value: $value);
    }

    protected function notifyRefresh(): void
    {
        $this->dispatch('refresh-requested');
    }

    protected function getParentModel(): ?Model
    {
        return $this->trainingPlan ?? null;
    }
}
