<?php

namespace App\Cms\Form;

use Closure;

class Action
{
    public ActionBehavior $behavior = ActionBehavior::Direct;

    public ActionPlacement $placement = ActionPlacement::Row;

    public ?string $icon = null;

    public ?string $variant = null;

    public ?string $handler = null;

    public ?string $formDataClass = null;

    public ?string $modalTitle = null;

    public ?string $submitLabel = null;

    public ?string $confirmHeading = null;

    public ?string $confirmDescription = null;

    public ?string $confirmButtonLabel = null;

    public ?string $confirmButtonVariant = null;

    public bool $passesItemData = false;

    public ?string $disabledWhen = null;

    public ?string $formComponent = null;

    public ?Closure $prepareData = null;

    public ?string $alpineEventName = null;

    public ?Closure $alpineEventData = null;

    public ?Closure $visibleWhen = null;

    public function __construct(
        public string $name,
        public string $label,
    ) {}

    public static function make(string $name, string $label): static
    {
        return new static($name, $label);
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function variant(string $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function header(): static
    {
        $this->placement = ActionPlacement::Header;

        return $this;
    }

    public function row(): static
    {
        $this->placement = ActionPlacement::Row;

        return $this;
    }

    public function rowMenu(): static
    {
        $this->placement = ActionPlacement::RowMenu;

        return $this;
    }

    public function handler(string $method): static
    {
        $this->handler = $method;

        return $this;
    }

    public function formModal(string $formDataClass, ?string $title = null, ?string $submitLabel = null): static
    {
        $this->behavior = ActionBehavior::FormModal;
        $this->formDataClass = $formDataClass;
        $this->modalTitle = $title;
        $this->submitLabel = $submitLabel ?? 'Save';

        return $this;
    }

    public function confirm(?string $heading = null, ?string $description = null, ?string $buttonLabel = null): static
    {
        $this->behavior = ActionBehavior::Confirm;
        $this->confirmHeading = $heading;
        $this->confirmDescription = $description;
        $this->confirmButtonLabel = $buttonLabel ?? 'Confirm';
        $this->confirmButtonVariant = 'danger';

        return $this;
    }

    public function confirmVariant(string $variant): static
    {
        $this->confirmButtonVariant = $variant;

        return $this;
    }

    public function passesItemData(bool $passes = true): static
    {
        $this->passesItemData = $passes;

        return $this;
    }

    public function disabledWhen(string $condition): static
    {
        $this->disabledWhen = $condition;

        return $this;
    }

    public function formComponent(string $component): static
    {
        $this->formComponent = $component;

        return $this;
    }

    public function prepareData(Closure $callback): static
    {
        $this->prepareData = $callback;

        return $this;
    }

    public function alpineEvent(string $eventName, Closure $dataCallback): static
    {
        $this->behavior = ActionBehavior::AlpineEvent;
        $this->alpineEventName = $eventName;
        $this->alpineEventData = $dataCallback;

        return $this;
    }

    public function visibleWhen(Closure $callback): static
    {
        $this->visibleWhen = $callback;

        return $this;
    }

    public function isAlpineEvent(): bool
    {
        return $this->behavior === ActionBehavior::AlpineEvent;
    }

    public function isVisible(mixed $item): bool
    {
        if ($this->visibleWhen === null) {
            return true;
        }

        return ($this->visibleWhen)($item);
    }

    public function getAlpineEventData(mixed $item): array
    {
        if ($this->alpineEventData === null) {
            return [];
        }

        return ($this->alpineEventData)($item);
    }

    public function resolveModalName(string $entitySlug): string
    {
        return $this->name.'-'.$entitySlug;
    }

    public function getHandler(): string
    {
        return $this->handler ?? $this->name;
    }

    public function isFormModal(): bool
    {
        return $this->behavior === ActionBehavior::FormModal;
    }

    public function isConfirm(): bool
    {
        return $this->behavior === ActionBehavior::Confirm;
    }

    public function isDirect(): bool
    {
        return $this->behavior === ActionBehavior::Direct;
    }
}
