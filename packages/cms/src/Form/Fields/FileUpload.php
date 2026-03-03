<?php

namespace Coda\Cms\Form\Fields;

use Coda\Cms\Form\Field;

class FileUpload extends Field
{
    public string $type = 'file-upload';

    public bool $multiple = false;

    public ?string $accept = null;

    public ?int $maxFileSize = null;

    public string $collection = 'default';

    public string $dropzoneHeading = 'Drop files here or click to browse';

    public string $dropzoneText = 'JPG, PNG, GIF up to 10MB';

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

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
}
