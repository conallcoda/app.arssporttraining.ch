<?php

namespace Coda\SchemaKit;

use Closure;

class IdentityImageDefinition
{
    private Closure|string|null $field = null;

    private Closure|string|null $mediaUuid = null;

    private Closure|string|null $mediaVersion = null;

    private Closure|string|null $focusPoint = null;

    private ?string $preset = null;

    private array $widths = [];

    private ?string $sizes = null;

    private bool $square = false;

    private bool $initialsFallback = true;

    public function __construct(Closure|string|null $field = null)
    {
        $this->field = $field;
    }

    public static function make(Closure|string|null $field = null): static
    {
        return new static($field);
    }

    public function field(Closure|string|null $field): static
    {
        $this->field = $field;

        return $this;
    }

    public function mediaUuid(Closure|string|null $mediaUuid): static
    {
        $this->mediaUuid = $mediaUuid;

        return $this;
    }

    public function mediaVersion(Closure|string|null $mediaVersion): static
    {
        $this->mediaVersion = $mediaVersion;

        return $this;
    }

    public function focusPoint(Closure|string|null $focusPoint): static
    {
        $this->focusPoint = $focusPoint;

        return $this;
    }

    public function preset(?string $preset): static
    {
        $this->preset = $preset;

        return $this;
    }

    public function widths(array $widths): static
    {
        $this->widths = array_values($widths);

        return $this;
    }

    public function sizes(?string $sizes): static
    {
        $this->sizes = $sizes;

        return $this;
    }

    public function square(bool $square = true): static
    {
        $this->square = $square;

        return $this;
    }

    public function initialsFallback(bool $initialsFallback = true): static
    {
        $this->initialsFallback = $initialsFallback;

        return $this;
    }

    public function getField(): Closure|string|null
    {
        return $this->field;
    }

    public function getMediaUuid(): Closure|string|null
    {
        return $this->mediaUuid;
    }

    public function getMediaVersion(): Closure|string|null
    {
        return $this->mediaVersion;
    }

    public function getFocusPoint(): Closure|string|null
    {
        return $this->focusPoint;
    }

    public function getPreset(): ?string
    {
        return $this->preset;
    }

    public function getWidths(): array
    {
        return $this->widths;
    }

    public function getSizes(): ?string
    {
        return $this->sizes;
    }

    public function isSquare(): bool
    {
        return $this->square;
    }

    public function useInitialsFallback(): bool
    {
        return $this->initialsFallback;
    }
}
