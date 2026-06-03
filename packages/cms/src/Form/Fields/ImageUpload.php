<?php

namespace Coda\Cms\Form\Fields;

use Coda\Cms\Support\MediaPresetRegistry;

class ImageUpload extends \Coda\FormKit\Fields\ImageUpload
{
    public function previewPreset(?string $preset, ?int $width = null): static
    {
        parent::previewPreset($preset, $width);

        if ($preset === null) {
            return $this;
        }

        $definition = app(MediaPresetRegistry::class)->get($preset);

        return $this->previewAspectRatio($definition['preview_aspect_ratio'])
            ->previewCrop($definition['crop'])
            ->previewUseFocusPoint($definition['use_focus_point']);
    }

    public function squarePreview(): static
    {
        return $this->previewPreset('square');
    }
}
