<?php

namespace Coda\Cms\Support;

use InvalidArgumentException;

class MediaPresetRegistry
{
    public function all(): array
    {
        $configured = config('cms.media.presets', []);

        return array_replace_recursive($this->defaults(), is_array($configured) ? $configured : []);
    }

    public function get(string $name): array
    {
        $preset = $this->all()[$name] ?? null;

        if (! is_array($preset)) {
            throw new InvalidArgumentException("Unknown media preset [{$name}].");
        }

        $ratio = $preset['ratio'] ?? null;

        if (! is_string($ratio) || ! preg_match('/^\d+:\d+$/', $ratio)) {
            throw new InvalidArgumentException("Media preset [{$name}] must define a ratio like 1:1.");
        }

        [$ratioWidth, $ratioHeight] = array_map('intval', explode(':', $ratio, 2));
        $allowedWidths = array_values(array_unique(array_map('intval', $preset['allowed_widths'] ?? [])));
        sort($allowedWidths);

        return [
            'name' => $name,
            'ratio' => $ratio,
            'ratio_width' => $ratioWidth,
            'ratio_height' => $ratioHeight,
            'preview_aspect_ratio' => $preset['preview_aspect_ratio'] ?? "{$ratioWidth} / {$ratioHeight}",
            'crop' => (bool) ($preset['crop'] ?? true),
            'use_focus_point' => (bool) ($preset['use_focus_point'] ?? false),
            'format' => (string) ($preset['format'] ?? 'webp'),
            'quality' => (int) ($preset['quality'] ?? 90),
            'allowed_widths' => $allowedWidths,
            'default_width' => (int) ($preset['default_width'] ?? ($allowedWidths[0] ?? 0)),
        ];
    }

    public function normalizeWidth(string $name, ?int $requestedWidth = null): int
    {
        $preset = $this->get($name);
        $allowedWidths = $preset['allowed_widths'];

        if ($allowedWidths === []) {
            return max(1, $requestedWidth ?? $preset['default_width']);
        }

        $requestedWidth = $requestedWidth && $requestedWidth > 0
            ? $requestedWidth
            : $preset['default_width'];

        foreach ($allowedWidths as $allowedWidth) {
            if ($requestedWidth <= $allowedWidth) {
                return $allowedWidth;
            }
        }

        return end($allowedWidths);
    }

    protected function defaults(): array
    {
        return [
            'square' => [
                'ratio' => '1:1',
                'preview_aspect_ratio' => '1 / 1',
                'crop' => true,
                'use_focus_point' => true,
                'format' => 'webp',
                'quality' => 90,
                'default_width' => 80,
                'allowed_widths' => [40, 80, 120, 160, 240, 320, 480, 640],
            ],
        ];
    }
}
