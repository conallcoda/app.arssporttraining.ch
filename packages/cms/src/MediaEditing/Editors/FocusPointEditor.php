<?php

namespace Coda\Cms\MediaEditing\Editors;

use Coda\Cms\MediaEditing\Contracts\MediaEditor;
use Coda\Cms\Models\Media;

class FocusPointEditor implements MediaEditor
{
    public static function key(): string
    {
        return 'focus';
    }

    public static function label(): string
    {
        return 'Focus Point';
    }

    public function view(): string
    {
        return 'cms::media-editors.focus-point';
    }

    public function initialState(array $context = []): array
    {
        $state = $context['draft'] ?? data_get($context, 'media.customProperties.focus_point') ?? [];

        return $this->normalizeState($state);
    }

    public function normalizeState(array $state): array
    {
        $x = is_numeric($state['x'] ?? null) ? (float) $state['x'] : 0.5;
        $y = is_numeric($state['y'] ?? null) ? (float) $state['y'] : 0.5;

        return [
            'x' => (float) max(0, min(1, $x)),
            'y' => (float) max(0, min(1, $y)),
        ];
    }

    public function persist(Media $media, array $state): void
    {
        $media->setCustomProperty('focus_point', $this->normalizeState($state));
        $media->save();
    }
}
