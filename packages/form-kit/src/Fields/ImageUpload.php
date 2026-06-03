<?php

namespace Coda\FormKit\Fields;

class ImageUpload extends FileUpload
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->accept = 'image';
        $this->editors = ['focus'];
        $this->defaultEditor = 'focus';
    }

    public function focusEditor(): static
    {
        return $this->editors(['focus'])->defaultEditor('focus');
    }

    public function squarePreview(): static
    {
        return $this->previewAspectRatio('1 / 1')
            ->previewCrop()
            ->previewUseFocusPoint();
    }
}
