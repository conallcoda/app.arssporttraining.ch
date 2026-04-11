<?php

namespace Coda\Cms\Livewire\Concerns;

use Coda\FormKit\Fields\FileUpload;
use Coda\FormKit\FormFieldset;
use Spatie\MediaLibrary\HasMedia;

trait InteractsWithMediaUploads
{
    public array $mediaUploads = [];

    public array $existingMedia = [];

    public array $removedMediaIds = [];

    /** @return FileUpload[] */
    protected function getFileUploadFields(): array
    {
        return collect($this->flatFieldsets())
            ->flatMap(fn (FormFieldset $fs) => $fs->fields)
            ->filter(fn ($field) => $field->type === 'file-upload')
            ->values()
            ->all();
    }

    protected function hasFileUploadFields(): bool
    {
        return ! empty($this->getFileUploadFields());
    }

    protected function loadAllExistingMedia(HasMedia $model): void
    {
        foreach ($this->getFileUploadFields() as $field) {
            $this->existingMedia[$field->name] = $model->getMedia($field->collection)
                ->map(fn ($media) => [
                    'id' => $media->id,
                    'name' => $media->name,
                    'url' => $media->getUrl(),
                    'size' => $media->size,
                ])
                ->toArray();
        }
    }

    protected function clearAllMediaState(): void
    {
        $this->mediaUploads = [];
        $this->existingMedia = [];
        $this->removedMediaIds = [];
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
        $uploads = $this->mediaUploads[$fieldName] ?? [];

        if (is_array($uploads) && isset($uploads[$index])) {
            $uploads[$index]->delete();
            unset($uploads[$index]);
            $this->mediaUploads[$fieldName] = array_values($uploads);
        } elseif (! is_array($uploads) && $uploads) {
            $uploads->delete();
            $this->mediaUploads[$fieldName] = null;
        }
    }

    protected function persistAllMedia(HasMedia $model): void
    {
        if (! empty($this->removedMediaIds)) {
            $model->media()->whereIn('id', $this->removedMediaIds)->delete();
        }

        foreach ($this->getFileUploadFields() as $field) {
            $uploads = $this->mediaUploads[$field->name] ?? [];

            if (! is_array($uploads)) {
                $uploads = $uploads ? [$uploads] : [];
            }

            foreach ($uploads as $upload) {
                $model->addMedia($upload->getRealPath())
                    ->usingName($upload->getClientOriginalName())
                    ->toMediaCollection($field->collection);
            }
        }
    }

    protected function buildMediaValidationRules(): array
    {
        $rules = [];

        foreach ($this->getFileUploadFields() as $field) {
            $maxSize = $field->maxFileSize ?? 10240;
            $accept = $field->accept ?? 'image';
            $rules["mediaUploads.{$field->name}.*"] = [$accept, "max:{$maxSize}"];
        }

        return $rules;
    }
}
