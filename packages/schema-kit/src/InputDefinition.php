<?php

namespace Coda\SchemaKit;

abstract class InputDefinition
{
    private ?string $updateOn = null;

    private ?int $debounceUpdate = null;

    private ?string $placeholder = null;

    private mixed $default = null;

    private ?string $visibleWhen = null;

    private bool $disabled = false;

    private bool $readonly = false;

    private array $meta = [];

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function visibleWhen(?string $expression): static
    {
        $this->visibleWhen = $expression;

        return $this;
    }

    public function disabled(bool $disabled = true): static
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function readonly(bool $readonly = true): static
    {
        $this->readonly = $readonly;

        return $this;
    }

    public function updateOn(string $mode, ?int $debounceMs = null): static
    {
        if (! in_array($mode, ['default', 'live', 'blur', 'change'], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported update mode [%s].', $mode));
        }

        $this->updateOn = $mode;
        $this->debounceUpdate = $mode === 'live' ? $debounceMs : null;

        return $this;
    }

    public function debounceUpdate(int $milliseconds): static
    {
        $this->updateOn = 'live';
        $this->debounceUpdate = $milliseconds;

        return $this;
    }

    public function live(bool $live = true): static
    {
        if (! $live) {
            return $this->updateOn('default');
        }

        return $this->updateOn('live', $this->debounceUpdate);
    }

    public function blur(bool $blur = true): static
    {
        if (! $blur) {
            return $this->updateOn('default');
        }

        return $this->updateOn('blur');
    }

    public function change(bool $change = true): static
    {
        if (! $change) {
            return $this->updateOn('default');
        }

        return $this->updateOn('change');
    }

    public function debounce(int $milliseconds): static
    {
        return $this->debounceUpdate($milliseconds);
    }

    public function setMeta(string $key, mixed $value): static
    {
        $this->meta[$key] = $value;

        return $this;
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    public function allMeta(): array
    {
        return $this->meta;
    }

    public function getUpdateOn(): ?string
    {
        return $this->updateOn;
    }

    public function getDebounceUpdate(): ?int
    {
        return $this->debounceUpdate;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function getVisibleWhen(): ?string
    {
        return $this->visibleWhen;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    abstract public function kind(): string;
}
