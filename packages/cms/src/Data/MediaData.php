<?php

namespace Coda\Cms\Data;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class MediaData extends AbstractData
{
    public function __construct(
        public ?int $id = null,
        public ?string $uuid = null,
        public string $name = '',
        public string $file_name = '',
        public ?string $mime_type = null,
        public ?string $type = null,
        public ?string $size_label = null,
        public ?string $url = null,
        public ?string $preview_url = null,
        public ?Carbon $updated_at = null,
    ) {}

    public static function fromModel(Model $model): self
    {
        abort_unless($model instanceof Media, 500);

        $isImage = str_starts_with((string) $model->mime_type, 'image/');

        return new self(
            id: $model->id,
            uuid: $model->uuid,
            name: $model->name ?: $model->file_name,
            file_name: $model->file_name,
            mime_type: $model->mime_type,
            type: $model->mime_type ?: 'File',
            size_label: self::formatBytes((int) $model->size),
            url: $model->getUrl(),
            preview_url: $isImage ? $model->getUrl() : null,
            updated_at: $model->updated_at,
        );
    }

    public static function getEntityName(): string
    {
        return 'Media';
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }
}
