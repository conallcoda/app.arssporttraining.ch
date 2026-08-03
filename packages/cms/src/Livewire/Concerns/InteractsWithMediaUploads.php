<?php

namespace Coda\Cms\Livewire\Concerns;

use Coda\Cms\MediaEditing\MediaEditorRegistry;
use Coda\FormKit\Fields\FileUpload;
use Coda\FormKit\FormFieldset;
use Flux\Flux;
use InvalidArgumentException;
use Spatie\MediaLibrary\HasMedia;

trait InteractsWithMediaUploads
{
    public array $mediaUploads = [];

    public array $existingMedia = [];

    public array $removedMediaIds = [];

    public ?array $activeMediaEditor = null;

    public array $mediaEditorState = [];

    public array $mediaEditorDrafts = [];

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
                    'name' => $media->file_name,
                    'url' => $field->previewPreset && method_exists($media, 'getPresetUrl')
                        ? $media->getPresetUrl($field->previewPreset, $field->previewPresetWidth)
                        : $media->getUrl(),
                    'originalUrl' => $media->getUrl(),
                    'size' => $media->size,
                    'customProperties' => $media->custom_properties ?? [],
                    'focusPoint' => $media->focus_point,
                ])
                ->toArray();
        }
    }

    protected function clearAllMediaState(): void
    {
        $this->mediaUploads = [];
        $this->existingMedia = [];
        $this->removedMediaIds = [];
        $this->activeMediaEditor = null;
        $this->mediaEditorState = [];
        $this->mediaEditorDrafts = [];
    }

    protected function getFileUploadField(string $fieldName): ?FileUpload
    {
        foreach ($this->getFileUploadFields() as $field) {
            if ($field->name === $fieldName) {
                return $field;
            }
        }

        return null;
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
        $draftKey = $this->mediaUploadDraftKey($fieldName, $index);

        if (isset($this->mediaEditorDrafts[$fieldName][$draftKey])) {
            unset($this->mediaEditorDrafts[$fieldName][$draftKey]);
        }

        if (is_array($uploads) && isset($uploads[$index])) {
            $uploads[$index]->delete();
            unset($uploads[$index]);
            $this->mediaUploads[$fieldName] = array_values($uploads);
        } elseif (! is_array($uploads) && $uploads) {
            $uploads->delete();
            $this->mediaUploads[$fieldName] = null;
        }
    }

    public function updatedMediaUploads(mixed $value, string $key): void
    {
        $fieldName = explode('.', $key)[0] ?? null;

        if (! is_string($fieldName) || $fieldName === '') {
            return;
        }

        $field = $this->getFileUploadField($fieldName);

        if (! $field || $field->multiple) {
            return;
        }

        if (is_array($value)) {
            $this->mediaUploads[$fieldName] = end($value) ?: null;
        }
    }

    public function openExistingMediaEditor(string $fieldName, int $mediaId, ?string $editor = null): void
    {
        $field = $this->getFileUploadField($fieldName);

        if (! $field) {
            throw new InvalidArgumentException("Unknown media field [{$fieldName}].");
        }

        $editorKey = $this->resolveMediaEditorKey($field, $editor);

        if ($editorKey === null) {
            return;
        }

        $media = collect($this->existingMedia[$fieldName] ?? [])->firstWhere('id', $mediaId);

        if (! is_array($media)) {
            throw new InvalidArgumentException("Unknown media item [{$mediaId}] for field [{$fieldName}].");
        }

        $editorInstance = $this->mediaEditorRegistry()->make($editorKey);

        $this->activeMediaEditor = [
            'fieldName' => $fieldName,
            'fieldLabel' => $field->getLabel(),
            'editor' => $editorKey,
            'editorLabel' => $editorInstance::label(),
            'targetType' => 'existing',
            'targetId' => $mediaId,
            'previewUrl' => $media['originalUrl'] ?? ($media['url'] ?? null),
            'modalName' => $this->mediaEditorModalName($fieldName),
        ];
        $this->mediaEditorState = $editorInstance->initialState([
            'field' => $field,
            'media' => $media,
        ]);

        Flux::modal($this->activeMediaEditor['modalName'])->show();
    }

    public function openNewMediaEditor(string $fieldName, int $index, ?string $editor = null): void
    {
        $field = $this->getFileUploadField($fieldName);

        if (! $field) {
            throw new InvalidArgumentException("Unknown media field [{$fieldName}].");
        }

        $editorKey = $this->resolveMediaEditorKey($field, $editor);

        if ($editorKey === null) {
            return;
        }

        $upload = $this->resolveUploadByIndex($fieldName, $index);

        if (! $upload || ! method_exists($upload, 'isPreviewable') || ! $upload->isPreviewable()) {
            return;
        }

        $draftKey = $this->mediaUploadDraftKey($fieldName, $index);
        $editorInstance = $this->mediaEditorRegistry()->make($editorKey);

        $this->activeMediaEditor = [
            'fieldName' => $fieldName,
            'fieldLabel' => $field->getLabel(),
            'editor' => $editorKey,
            'editorLabel' => $editorInstance::label(),
            'targetType' => 'new',
            'targetId' => $index,
            'draftKey' => $draftKey,
            'previewUrl' => $upload->temporaryUrl(),
            'modalName' => $this->mediaEditorModalName($fieldName),
        ];
        $this->mediaEditorState = $editorInstance->initialState([
            'field' => $field,
            'draft' => $this->mediaEditorDrafts[$fieldName][$draftKey][$editorKey] ?? null,
        ]);

        Flux::modal($this->activeMediaEditor['modalName'])->show();
    }

    public function saveMediaEditor(): void
    {
        if ($this->activeMediaEditor === null) {
            return;
        }

        $editorInstance = $this->mediaEditorRegistry()->make($this->activeMediaEditor['editor']);
        $normalizedState = $editorInstance->normalizeState($this->mediaEditorState);

        if ($this->activeMediaEditor['targetType'] === 'existing') {
            $mediaModelClass = $this->mediaModelClass();

            /** @var \Spatie\MediaLibrary\MediaCollections\Models\Media|null $media */
            $media = $mediaModelClass::query()->find($this->activeMediaEditor['targetId']);

            if ($media) {
                $editorInstance->persist($media, $normalizedState);

                $this->existingMedia[$this->activeMediaEditor['fieldName']] = collect($this->existingMedia[$this->activeMediaEditor['fieldName']] ?? [])
                    ->map(function (array $item) use ($media): array {
                        if ((int) $item['id'] !== (int) $media->id) {
                            return $item;
                        }

                        $field = $this->getFileUploadField($this->activeMediaEditor['fieldName']);
                        $previewUrl = $field && $field->previewPreset && method_exists($media, 'getPresetUrl')
                            ? $media->getPresetUrl($field->previewPreset, $field->previewPresetWidth)
                            : $media->getUrl();

                        return array_replace($item, [
                            'url' => $previewUrl,
                            'originalUrl' => $media->getUrl(),
                            'customProperties' => $media->custom_properties ?? [],
                            'focusPoint' => $media->focus_point,
                        ]);
                    })
                    ->values()
                    ->all();
            }
        } else {
            $fieldName = $this->activeMediaEditor['fieldName'];
            $draftKey = $this->activeMediaEditor['draftKey'];
            $editorKey = $this->activeMediaEditor['editor'];

            $this->mediaEditorDrafts[$fieldName][$draftKey][$editorKey] = $normalizedState;
        }

        $this->mediaEditorState = $normalizedState;

        Flux::modal($this->activeMediaEditor['modalName'])->close();
        $this->closeMediaEditor();
    }

    public function cancelMediaEditor(): void
    {
        if ($this->activeMediaEditor !== null) {
            Flux::modal($this->activeMediaEditor['modalName'])->close();
        }

        $this->closeMediaEditor();
    }

    public function closeMediaEditor(): void
    {
        $this->activeMediaEditor = null;
        $this->mediaEditorState = [];
    }

    public function activeMediaEditorView(): ?string
    {
        if ($this->activeMediaEditor === null) {
            return null;
        }

        return $this->mediaEditorRegistry()
            ->make($this->activeMediaEditor['editor'])
            ->view();
    }

    public function activeMediaEditorTitle(): string
    {
        if ($this->activeMediaEditor === null) {
            return 'Edit Media';
        }

        return "{$this->activeMediaEditor['editorLabel']}: {$this->activeMediaEditor['fieldLabel']}";
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
                $media = $model->addMedia($upload->getRealPath())
                    ->usingName($upload->getClientOriginalName())
                    ->toMediaCollection($field->collection);

                $draftKey = $this->mediaUploadDraftKeyForUpload($upload);

                foreach ($this->mediaEditorDrafts[$field->name][$draftKey] ?? [] as $editorKey => $state) {
                    $this->mediaEditorRegistry()->make($editorKey)->persist($media, $state);
                }
            }
        }
    }

    protected function buildMediaValidationRules(): array
    {
        $rules = [];

        foreach ($this->getFileUploadFields() as $field) {
            $maxSize = $field->maxFileSize ?? 10240;
            $accept = $field->accept ?? 'image';

            if ($field->multiple) {
                $rules["mediaUploads.{$field->name}.*"] = [$accept, "max:{$maxSize}"];
            } else {
                $rules["mediaUploads.{$field->name}"] = [$accept, "max:{$maxSize}"];
            }
        }

        return $rules;
    }

    protected function mediaEditorRegistry(): MediaEditorRegistry
    {
        return app(MediaEditorRegistry::class);
    }

    protected function mediaModelClass(): string
    {
        return config('media-library.media_model', \Coda\Cms\Models\Media::class);
    }

    protected function resolveMediaEditorKey(FileUpload $field, ?string $editor = null): ?string
    {
        $editorKey = $editor ?? $field->defaultEditor ?? $field->editors[0] ?? null;

        if (! is_string($editorKey) || $editorKey === '') {
            return null;
        }

        if (! in_array($editorKey, $field->editors, true)) {
            throw new InvalidArgumentException("Media editor [{$editorKey}] is not available for field [{$field->name}].");
        }

        if (! $this->mediaEditorRegistry()->has($editorKey)) {
            throw new InvalidArgumentException("Media editor [{$editorKey}] has not been registered.");
        }

        return $editorKey;
    }

    protected function resolveUploadByIndex(string $fieldName, int $index): mixed
    {
        $uploads = $this->mediaUploads[$fieldName] ?? [];

        if (is_array($uploads)) {
            return $uploads[$index] ?? null;
        }

        return $index === 0 ? $uploads : null;
    }

    public function mediaUploadDraftKey(string $fieldName, int $index): string
    {
        return $this->mediaUploadDraftKeyForUpload($this->resolveUploadByIndex($fieldName, $index), $index);
    }

    public function mediaEditorModalName(string $fieldName): string
    {
        $componentId = method_exists($this, 'getId') ? $this->getId() : spl_object_id($this);

        return "media-editor-{$fieldName}-{$componentId}";
    }

    protected function mediaUploadDraftKeyForUpload(mixed $upload, ?int $fallbackIndex = null): string
    {
        if ($upload && method_exists($upload, 'getFilename')) {
            return 'tmp:'.$upload->getFilename();
        }

        return 'index:'.($fallbackIndex ?? 0);
    }
}
