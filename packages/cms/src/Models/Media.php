<?php

namespace Coda\Cms\Models;

use Coda\Cms\Support\MediaPresetRegistry;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Media extends SpatieMedia
{
    protected $hidden = ['inline_content'];

    protected $appends = ['focus_point'];

    public function scopeWithInlineContent($query)
    {
        return $query;
    }

    public function getDecodedInlineContent(): mixed
    {
        if ($this->inline_content === null) {
            return null;
        }

        if ($this->mime_type === 'application/json') {
            return json_decode($this->inline_content, true);
        }

        return $this->inline_content;
    }

    public function isInPlace(): bool
    {
        return in_array($this->disk, config('cms.media.in_place_disks', []), true);
    }

    public function getShowUrl(): string
    {
        return route('cms.media.show', [
            'media' => $this->getKey(),
            'filename' => $this->file_name,
        ]);
    }

    public function getPresetUrl(string $preset, ?int $width = null): string
    {
        $resolvedWidth = app(MediaPresetRegistry::class)->normalizeWidth($preset, $width);

        return route('media.presets.show', [
            'media' => $this->uuid,
            'version' => $this->presetVersionTimestamp(),
            'preset' => $preset,
            'w' => $resolvedWidth,
        ]);
    }

    public function getPresetSrcset(string $preset, array $widths): string
    {
        $registry = app(MediaPresetRegistry::class);

        return collect($widths)
            ->map(fn (mixed $width) => is_numeric($width) ? (int) $width : null)
            ->filter(fn (?int $width) => $width !== null && $width > 0)
            ->unique()
            ->values()
            ->map(function (int $width) use ($preset, $registry): string {
                $resolvedWidth = $registry->normalizeWidth($preset, $width);

                return "{$this->getPresetUrl($preset, $resolvedWidth)} {$resolvedWidth}w";
            })
            ->unique()
            ->implode(', ');
    }

    public function getFocusPointAttribute(): array
    {
        $focusPoint = $this->getCustomProperty('focus_point');

        $x = is_numeric($focusPoint['x'] ?? null) ? (float) $focusPoint['x'] : 0.5;
        $y = is_numeric($focusPoint['y'] ?? null) ? (float) $focusPoint['y'] : 0.5;

        return [
            'x' => max(0, min(1, $x)),
            'y' => max(0, min(1, $y)),
        ];
    }

    public function focusPointCssPosition(): string
    {
        $focusPoint = $this->focus_point;

        return ($focusPoint['x'] * 100).'% '.($focusPoint['y'] * 100).'%';
    }

    public function presetVersionTimestamp(): int
    {
        return $this->updated_at?->timestamp ?? 0;
    }
}
