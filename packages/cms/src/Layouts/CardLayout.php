<?php

namespace Coda\Cms\Layouts;

use Closure;
use Coda\Cms\Display\CardDefinition;

final class CardLayout
{
    private ?array $fields = null;

    private ?CardDefinition $definition = null;

    private string $layout = 'grid';

    private int $width = 260;

    private int|string|null $minWidth = null;

    private ?string $itemClass = null;

    private ?string $titleField = null;

    private ?string $view = null;

    private ?string $masonryOverlayView = null;

    private ?string $urlField = null;

    private ?Closure $urlUsing = null;

    private ?string $alternateImageField = null;

    private bool $lightbox = false;

    public static function make(): static
    {
        return new self;
    }

    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public function definition(CardDefinition $definition): static
    {
        $this->definition = $definition;

        return $this;
    }

    public function layout(string $layout): static
    {
        $this->layout = $layout;

        return $this;
    }

    public function width(int $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function minWidth(int|string $minWidth): static
    {
        $this->minWidth = $minWidth;

        return $this;
    }

    public function itemClass(?string $itemClass): static
    {
        $this->itemClass = $itemClass;

        return $this;
    }

    public function titleField(?string $titleField): static
    {
        $this->titleField = $titleField;

        return $this;
    }

    public function view(?string $view): static
    {
        $this->view = $view;

        return $this;
    }

    public function masonryOverlayView(?string $view): static
    {
        $this->masonryOverlayView = $view;

        return $this;
    }

    public function urlField(?string $field): static
    {
        $this->urlField = $field;

        return $this;
    }

    public function urlUsing(?callable $resolver): static
    {
        $this->urlUsing = $resolver instanceof Closure ? $resolver : ($resolver !== null ? $resolver(...) : null);

        return $this;
    }

    public function alternateImageField(?string $field): static
    {
        $this->alternateImageField = $field;

        return $this;
    }

    public function lightbox(bool $enabled = true): static
    {
        $this->lightbox = $enabled;

        return $this;
    }

    public function getFields(): ?array
    {
        return $this->fields;
    }

    public function getDefinition(): ?CardDefinition
    {
        return $this->definition;
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getMinWidth(): int|string|null
    {
        return $this->minWidth;
    }

    public function getItemClass(): ?string
    {
        return $this->itemClass;
    }

    public function getTitleField(): ?string
    {
        return $this->titleField;
    }

    public function getView(): ?string
    {
        return $this->view;
    }

    public function getMasonryOverlayView(): ?string
    {
        return $this->masonryOverlayView;
    }

    public function getUrlField(): ?string
    {
        return $this->urlField;
    }

    public function getUrlUsing(): ?Closure
    {
        return $this->urlUsing;
    }

    public function getAlternateImageField(): ?string
    {
        return $this->alternateImageField;
    }

    public function hasLightbox(): bool
    {
        return $this->lightbox;
    }
}
