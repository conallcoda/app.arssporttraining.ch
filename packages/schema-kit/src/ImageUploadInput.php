<?php

namespace Coda\SchemaKit;

final class ImageUploadInput extends InputDefinition
{
    private ?string $collection = null;

    private bool $single = false;

    private ?string $previewPreset = null;

    private ?int $previewPresetWidth = null;

    private ?int $maxFileSize = null;

    private ?string $dropzoneText = null;

    private ?string $factory = null;

    public static function make(): static
    {
        return new static;
    }

    public function collection(?string $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function single(bool $single = true): static
    {
        $this->single = $single;

        return $this;
    }

    public function previewPreset(?string $preset, ?int $width = null): static
    {
        $this->previewPreset = $preset;
        $this->previewPresetWidth = $width;

        return $this;
    }

    public function maxFileSize(?int $maxFileSize): static
    {
        $this->maxFileSize = $maxFileSize;

        return $this;
    }

    public function dropzoneText(?string $dropzoneText): static
    {
        $this->dropzoneText = $dropzoneText;

        return $this;
    }

    public function factory(?string $factory): static
    {
        $this->factory = $factory;

        return $this;
    }

    public function getCollection(): ?string
    {
        return $this->collection;
    }

    public function isSingle(): bool
    {
        return $this->single;
    }

    public function getPreviewPreset(): ?string
    {
        return $this->previewPreset;
    }

    public function getPreviewPresetWidth(): ?int
    {
        return $this->previewPresetWidth;
    }

    public function getMaxFileSize(): ?int
    {
        return $this->maxFileSize;
    }

    public function getDropzoneText(): ?string
    {
        return $this->dropzoneText;
    }

    public function getFactory(): ?string
    {
        return $this->factory;
    }

    public function kind(): string
    {
        return 'image_upload';
    }
}
