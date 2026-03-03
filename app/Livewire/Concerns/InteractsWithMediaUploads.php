<?php

namespace App\Livewire\Concerns;

use Spatie\MediaLibrary\HasMedia;

trait InteractsWithMediaUploads
{
    public array $existingMedia = [];

    public array $removedMediaIds = [];

    protected function loadExistingMedia(string $fieldName, HasMedia $model, string $collection): void
    {
        $this->existingMedia[$fieldName] = $model->getMedia($collection)
            ->map(fn ($media) => [
                'id' => $media->id,
                'name' => $media->file_name,
                'url' => $media->getUrl(),
                'size' => $media->size,
            ])
            ->toArray();
    }

    protected function clearMediaState(string $fieldName): void
    {
        $this->existingMedia[$fieldName] = [];
        $this->removedMediaIds = [];

        if (property_exists($this, $fieldName)) {
            $this->{$fieldName} = [];
        }
    }

    public function removeExistingMedia(string $fieldName, int $mediaId): void
    {
        $this->removedMediaIds[] = $mediaId;

        $this->existingMedia[$fieldName] = array_values(
            array_filter(
                $this->existingMedia[$fieldName] ?? [],
                fn (array $item) => $item['id'] !== $mediaId
            )
        );
    }

    public function removeNewUpload(string $fieldName, int $index): void
    {
        if (is_array($this->{$fieldName})) {
            $upload = $this->{$fieldName}[$index] ?? null;

            if ($upload) {
                $upload->delete();
            }

            unset($this->{$fieldName}[$index]);
            $this->{$fieldName} = array_values($this->{$fieldName});
        } else {
            if ($this->{$fieldName}) {
                $this->{$fieldName}->delete();
            }

            $this->{$fieldName} = null;
        }
    }

    protected function persistMedia(string $fieldName, HasMedia $model, string $collection): void
    {
        if (! empty($this->removedMediaIds)) {
            $model->media()->whereIn('id', $this->removedMediaIds)->delete();
        }

        $uploads = $this->{$fieldName} ?? [];

        if (! is_array($uploads)) {
            $uploads = $uploads ? [$uploads] : [];
        }

        foreach ($uploads as $upload) {
            $model->addMedia($upload->getRealPath())
                ->usingFileName($upload->getClientOriginalName())
                ->toMediaCollection($collection);
        }
    }
}
