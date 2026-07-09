<?php

namespace Coda\Cms\Models\Concerns;

use Coda\Cms\Models\Contracts\HasCollectionPaths;
use Coda\Cms\Models\Contracts\PersistsWithMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait HasCmsMedia
{
    use InteractsWithMedia;

    public function getCollectionBasePath(string $collectionName, ?Media $media = null): ?string
    {
        return null;
    }

    public static function resolveModel(int $id): ?Model
    {
        return static::find($id);
    }

    public function persist(): Model
    {
        $this->save();

        return $this;
    }

    public function addMediaInPlaceFromDisk(string $relativePath, string $disk, string $collection): Media
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($relativePath)) {
            throw new \RuntimeException("File not found on disk [{$disk}]: {$relativePath}");
        }

        $absolutePath = $storage->path($relativePath);
        $fileName = basename($relativePath);
        $name = pathinfo($fileName, PATHINFO_FILENAME);
        $mimeType = $storage->mimeType($relativePath) ?: null;
        $size = filesize($absolutePath);
        $mtime = filemtime($absolutePath);
        $hash = hash_file('xxh128', $absolutePath);

        $inlineContent = null;
        if ($this->shouldStoreInline($mimeType)) {
            $inlineContent = file_get_contents($absolutePath);
        }

        $mediaClass = $this->getMediaModel();

        /** @var Media $media */
        $media = new $mediaClass;
        $media->name = $name;
        $media->file_name = $fileName;
        $media->disk = $disk;
        $media->conversions_disk = $disk;
        $media->collection_name = $collection;
        $media->mime_type = $mimeType;
        $media->size = $size;
        $media->custom_properties = [
            'hash' => 'xxh128:'.$hash,
            'mtime' => $mtime,
        ];
        $media->manipulations = [];
        $media->generated_conversions = [];
        $media->responsive_images = [];

        if ($inlineContent !== null) {
            $media->inline_content = $inlineContent;
        }

        $this->media()->save($media);

        return $media;
    }

    public function addMediaInPlaceFromDiskIfChanged(string $relativePath, string $disk, string $collection, bool $force = false): Media
    {
        $fileName = basename($relativePath);

        $existing = $this->media()
            ->where('collection_name', $collection)
            ->where('file_name', $fileName)
            ->first();

        if ($existing && ! $force) {
            $absolutePath = Storage::disk($disk)->path($relativePath);
            $size = filesize($absolutePath);
            $mtime = filemtime($absolutePath);

            $props = $existing->custom_properties ?? [];
            if ((int) $existing->size === $size && ($props['mtime'] ?? null) === $mtime) {
                return $existing;
            }

            $hash = 'xxh128:'.hash_file('xxh128', $absolutePath);
            if (($props['hash'] ?? null) === $hash) {
                $existing->setCustomProperty('mtime', $mtime);
                $existing->save();

                return $existing;
            }
        }

        if ($existing) {
            $existing->delete();
        }

        return $this->addMediaInPlaceFromDisk($relativePath, $disk, $collection);
    }

    protected function shouldStoreInline(?string $mimeType): bool
    {
        if ($mimeType === null) {
            return false;
        }

        if ($mimeType === 'application/json') {
            return true;
        }

        return str_starts_with($mimeType, 'text/');
    }
}
