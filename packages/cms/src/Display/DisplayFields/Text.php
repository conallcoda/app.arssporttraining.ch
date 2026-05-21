<?php

namespace Coda\Cms\Display\DisplayFields;

use Coda\Cms\Display\Concerns\HasEnum;
use Coda\Cms\Display\Concerns\HasModal;
use Coda\Cms\Display\Concerns\HasPrefix;
use Coda\Cms\Display\Concerns\HasSuffix;
use Coda\Cms\Display\DisplayField;
use Illuminate\Support\Str;

class Text extends DisplayField
{
    use HasEnum;
    use HasModal;
    use HasPrefix;
    use HasSuffix;

    public string $type = 'text';

    public ?int $maxLength = 160;

    public bool $wrap = true;

    public bool $collapseWhitespace = true;

    public ?string $imageField = null;

    public ?string $imageFocusPointField = null;

    public ?string $imageMediaUuidField = null;

    public ?string $imageMediaVersionField = null;

    public ?string $imagePreset = null;

    public array $imageWidths = [];

    public ?string $imageSizes = null;

    public bool $imageSquare = false;

    public bool $imageInitialsFallback = true;

    public function maxLength(?int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    public function wrap(bool $wrap = true): static
    {
        $this->wrap = $wrap;

        return $this;
    }

    public function collapseWhitespace(bool $collapseWhitespace = true): static
    {
        $this->collapseWhitespace = $collapseWhitespace;

        return $this;
    }

    public function image(?string $imageField): static
    {
        $this->imageField = $imageField;

        return $this;
    }

    public function imageFocusPoint(?string $field): static
    {
        $this->imageFocusPointField = $field;

        return $this;
    }

    public function imageMediaUuid(?string $field): static
    {
        $this->imageMediaUuidField = $field;

        return $this;
    }

    public function imageMediaVersion(?string $field): static
    {
        $this->imageMediaVersionField = $field;

        return $this;
    }

    public function imagePreset(?string $preset): static
    {
        $this->imagePreset = $preset;

        return $this;
    }

    public function imageWidth(?int $width): static
    {
        $this->imageWidths = $width ? [$width] : [];

        return $this;
    }

    public function imageWidths(array $widths): static
    {
        $this->imageWidths = array_values($widths);

        return $this;
    }

    public function imageSizes(?string $sizes): static
    {
        $this->imageSizes = $sizes;

        return $this;
    }

    public function imageSquare(bool $square = true): static
    {
        $this->imageSquare = $square;

        return $this;
    }

    public function imageInitialsFallback(bool $fallback = true): static
    {
        $this->imageInitialsFallback = $fallback;

        return $this;
    }

    public function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            $value = collect($value)
                ->filter(fn (mixed $item) => $item !== null && $item !== '')
                ->map(fn (mixed $item) => is_scalar($item) ? (string) $item : json_encode($item))
                ->implode(', ');
        }

        $value = (string) $value;

        if ($this->collapseWhitespace) {
            $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        }

        if ($this->maxLength === null || mb_strlen($value) <= $this->maxLength) {
            return $value;
        }

        $truncated = mb_substr($value, 0, $this->maxLength + 1);
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace !== false && $lastSpace >= (int) floor($this->maxLength * 0.6)) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        } else {
            $truncated = mb_substr($truncated, 0, $this->maxLength);
        }

        return Str::of($truncated)
            ->rtrim(" \t\n\r\0\x0B,;:-")
            ->finish('…')
            ->toString();
    }
}
