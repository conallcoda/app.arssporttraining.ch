<?php

namespace Coda\Cms\Data;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class MediaRefData extends RefData
{
    public function __construct(
        ?string $type = 'media',
        int|string|null $id = null,
        ?string $label = null,
        public ?string $src = null,
        public ?string $uuid = null,
        public ?int $version = null,
        public ?string $focus_point = null,
        array $meta = [],
    ) {
        parent::__construct($type, $id, $label, $meta);
    }

    public static function fromMedia(?Media $media, ?string $label = null): ?self
    {
        if (! $media instanceof Media) {
            return null;
        }

        return new self(
            id: $media->getKey(),
            label: $label,
            src: $media->getUrl() ?: null,
            uuid: $media->uuid,
            version: $media->updated_at?->timestamp,
            focus_point: $media->focusPointCssPosition(),
        );
    }
}
