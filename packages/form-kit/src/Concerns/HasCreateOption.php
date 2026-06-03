<?php

namespace Coda\FormKit\Concerns;

use Closure;
use Livewire\Component;

trait HasCreateOption
{
    public ?string $createOptionFormDataClass = null;

    public ?string $createOptionLabel = null;

    public ?string $createOptionModalTitle = null;

    public string $createOptionSubmitLabel = 'Save';

    public array $createOptionExcludeFields = [];

    public bool $createOptionCloseParentAfterSubmit = false;

    protected array|Closure $createOptionContextData = [];

    protected ?Closure $createOptionSeedDataResolver = null;

    protected ?Closure $createOptionAttachResolver = null;

    public function createOption(string $formDataClass, ?string $label = null): static
    {
        $this->createOptionFormDataClass = $formDataClass;
        $this->createOptionLabel = $label;

        return $this;
    }

    public function createOptionLabel(string $label): static
    {
        $this->createOptionLabel = $label;

        return $this;
    }

    public function createOptionModalTitle(string $title): static
    {
        $this->createOptionModalTitle = $title;

        return $this;
    }

    public function createOptionSubmitLabel(string $label): static
    {
        $this->createOptionSubmitLabel = $label;

        return $this;
    }

    public function createOptionContextData(array|Closure $contextData): static
    {
        $this->createOptionContextData = $contextData;

        return $this;
    }

    public function createOptionExcludeFields(array $fields): static
    {
        $this->createOptionExcludeFields = $fields;

        return $this;
    }

    public function createOptionSeedDataUsing(Closure $resolver): static
    {
        $this->createOptionSeedDataResolver = $resolver;

        return $this;
    }

    public function attachCreatedUsing(Closure $resolver): static
    {
        $this->createOptionAttachResolver = $resolver;

        return $this;
    }

    public function closeParentAfterCreate(bool $close = true): static
    {
        $this->createOptionCloseParentAfterSubmit = $close;

        return $this;
    }

    public function hasCreateOption(): bool
    {
        return is_string($this->createOptionFormDataClass) && $this->createOptionFormDataClass !== '';
    }

    public function getCreateOptionLabel(): string
    {
        return $this->createOptionLabel ?? 'Create new';
    }

    public function getCreateOptionModalTitle(): string
    {
        return $this->createOptionModalTitle ?? $this->getCreateOptionLabel();
    }

    public function resolveCreateOptionContextData(array $context = []): array
    {
        if ($this->createOptionContextData instanceof Closure) {
            return (array) ($this->createOptionContextData)($context);
        }

        return $this->createOptionContextData;
    }

    public function resolveCreateOptionSeedData(?string $search = null, array $context = [], ?int $index = null, ?Component $component = null): array
    {
        if ($this->createOptionSeedDataResolver === null) {
            return [];
        }

        return (array) ($this->createOptionSeedDataResolver)($search, $context, $index, $component);
    }

    public function resolveCreatedOptionAttachment(array $submittedData, array $context = [], ?int $index = null, ?Component $component = null): mixed
    {
        if ($this->createOptionAttachResolver !== null) {
            return ($this->createOptionAttachResolver)($submittedData, $context, $index, $component);
        }

        return $submittedData[$this->valueAttribute ?? 'id'] ?? $submittedData['id'] ?? null;
    }
}
