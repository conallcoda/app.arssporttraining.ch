<?php

namespace Coda\Cms\Livewire\Concerns;

use Coda\Cms\Registry;
use Illuminate\Database\Eloquent\Model;

trait InteractsWithScopeContext
{
    public ?string $scopeAreaKey = null;

    public string|int|null $scopeContextRouteValue = null;

    /**
     * @var array<string, array{areaKey: string, routeValue: mixed, routeKey?: string}>
     */
    public array $scopeBindingsSnapshot = [];

    protected function currentScopeContext(): mixed
    {
        if ($this->scopeAreaKey !== null) {
            $area = app(Registry::class)->area($this->scopeAreaKey);

            if ($area?->isScoped()) {
                return $area->resolveCurrentContext($this->scopeContextRouteValue);
            }
        }

        return app(Registry::class)->currentContext();
    }

    /**
     * @return array<string, array{areaKey: string, routeValue: mixed, routeKey?: string}>
     */
    protected function currentScopeBindings(): array
    {
        if ($this->scopeBindingsSnapshot !== []) {
            return $this->scopeBindingsSnapshot;
        }

        $registry = app(Registry::class);
        $bindings = $registry->activeScopeBindings();

        if ($bindings !== []) {
            return $bindings;
        }

        if ($this->scopeAreaKey === null) {
            return [];
        }

        $area = $registry->area($this->scopeAreaKey);

        if (! $area?->isScoped()) {
            return [];
        }

        return [
            $area->scopeParam => [
                'areaKey' => $area->key,
                'routeValue' => $this->scopeContextRouteValue,
                'routeKey' => $area->scopeRouteKey,
            ],
        ];
    }

    protected function currentScopeBindingContext(string $binding): mixed
    {
        $bindings = $this->currentScopeBindings();
        $metadata = $bindings[$binding] ?? null;

        if (! is_array($metadata) || ! is_string($metadata['areaKey'] ?? null)) {
            return null;
        }

        $area = app(Registry::class)->area($metadata['areaKey']);

        if (! $area?->isScoped()) {
            return null;
        }

        return $area->resolveCurrentContext($metadata['routeValue'] ?? null);
    }

    protected function currentScopeBindingValue(string $binding, string $path = 'id', mixed $default = null): mixed
    {
        $context = $this->currentScopeBindingContext($binding);

        if ($context instanceof Model && ($path === 'id' || $path === $context->getKeyName())) {
            return $context->getKey();
        }

        return data_get($context, $path, $default);
    }

    protected function currentScopeValue(string $path = 'id', mixed $default = null): mixed
    {
        $context = $this->currentScopeContext();

        if ($context instanceof Model && ($path === 'id' || $path === $context->getKeyName())) {
            return $context->getKey();
        }

        return data_get($context, $path, $default);
    }

    protected function captureScopeSnapshot(): void
    {
        if ($this->scopeAreaKey !== null || $this->scopeBindingsSnapshot !== []) {
            return;
        }

        $registry = app(Registry::class);
        $bindings = $registry->activeScopeBindings();

        if ($bindings === []) {
            return;
        }

        $this->scopeBindingsSnapshot = $bindings;

        $first = reset($bindings);

        if (is_array($first) && is_string($first['areaKey'] ?? null)) {
            $this->scopeAreaKey = $first['areaKey'];
            $this->scopeContextRouteValue = $first['routeValue'] ?? null;
        }
    }
}
