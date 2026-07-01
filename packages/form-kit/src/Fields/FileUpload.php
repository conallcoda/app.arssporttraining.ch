<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Field;

class FileUpload extends Field
{
    public string $type = 'file-upload';

    public bool $multiple = false;

    public array $editors = [];

    public ?string $defaultEditor = null;

    public ?string $accept = null;

    public ?int $maxFileSize = null;

    public string $collection = 'default';

    public string $dropzoneHeading = 'Drop files here or click to browse';

    public string $dropzoneText = 'JPG, PNG, GIF up to 10MB';

    public string $previewMaxWidth = 'max-w-[80%] md:max-w-3xs';

    public ?string $previewPreset = null;

    public ?int $previewPresetWidth = null;

    public ?string $previewAspectRatio = null;

    public bool $previewCropsImage = false;

    public bool $previewUsesFocusPoint = false;

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function single(bool $single = true): static
    {
        $this->multiple = ! $single;

        return $this;
    }

    public function editors(array $editors): static
    {
        $this->editors = array_values($editors);

        return $this;
    }

    public function defaultEditor(?string $editor): static
    {
        $this->defaultEditor = $editor;

        return $this;
    }

    public function accept(string $accept): static
    {
        $this->accept = $accept;

        return $this;
    }

    public function maxFileSize(int $maxKb): static
    {
        $this->maxFileSize = $maxKb;

        return $this;
    }

    public function collection(string $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function dropzoneHeading(string $heading): static
    {
        $this->dropzoneHeading = $heading;

        return $this;
    }

    public function dropzoneText(string $text): static
    {
        $this->dropzoneText = $text;

        return $this;
    }

    public function previewMaxWidth(string $classes): static
    {
        $this->previewMaxWidth = $classes;

        return $this;
    }

    public function previewPreset(?string $preset, ?int $width = null): static
    {
        $this->previewPreset = $preset;
        $this->previewPresetWidth = $width;

        return $this;
    }

    public function previewAspectRatio(?string $ratio): static
    {
        $this->previewAspectRatio = $ratio;

        return $this;
    }

    public function previewCrop(bool $crop = true): static
    {
        $this->previewCropsImage = $crop;

        return $this;
    }

    public function previewUseFocusPoint(bool $useFocusPoint = true): static
    {
        $this->previewUsesFocusPoint = $useFocusPoint;

        return $this;
    }
}
