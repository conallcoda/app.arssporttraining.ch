<?php

namespace App\Cms\Livewire\Concerns;

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

    protected function notifyChanged(string $domain, ?string $action = null): void
    {
        $this->dispatch('child-changed', domain: $domain, action: $action);
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
