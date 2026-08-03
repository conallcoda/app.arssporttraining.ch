@php
    $mediaModel = "mediaUploads.{$field->name}";
    $existingItems = $this->existingMedia[$field->name] ?? [];
    $newUploads = $this->mediaUploads[$field->name] ?? ($field->multiple ? [] : null);
    $visibleExistingItems = $field->multiple || ! $newUploads ? $existingItems : [];
    $hasSingleUpload = ! $field->multiple && (filled($newUploads) || count($visibleExistingItems) > 0);
    $hasEditors = ! empty($field->editors);
    $previewMaxWidthClasses = $field->previewMaxWidth;
    $previewAspectRatioClass = blank($field->previewAspectRatio)
        ? null
        : (str_starts_with($field->previewAspectRatio, 'aspect-')
            ? $field->previewAspectRatio
            : "aspect-[{$field->previewAspectRatio}]");
    $resolvePreviewObjectPosition = static function (?array $focusPoint, $useFocusPoint): string {
        if (! $useFocusPoint) {
            return '50% 50%';
        }

        $x = is_numeric($focusPoint['x'] ?? null) ? (float) $focusPoint['x'] : 0.5;
        $y = is_numeric($focusPoint['y'] ?? null) ? (float) $focusPoint['y'] : 0.5;

        $x = max(0, min(1, $x));
        $y = max(0, min(1, $y));

        return ($x * 100).'% '.($y * 100).'%';
    };
    $formatFileSize = static function ($size): ?string {
        if ($size === null || $size === '') {
            return null;
        }

        if (! is_numeric($size)) {
            return (string) $size;
        }

        $size = (int) $size;

        if ($size < 1024) {
            return round($size).' B';
        }

        if ($size < 1024 * 1024) {
            return round($size / 1024).' KB';
        }

        if ($size < 1024 * 1024 * 1024) {
            return round($size / 1024 / 1024).' MB';
        }

        return round($size / 1024 / 1024 / 1024).' GB';
    };
@endphp

<x-form-kit::form.field-shell :field="$field" :error-name="$mediaModel" {{ $attributes }}>
    @if ($field->multiple)
        <flux:file-upload wire:model="{{ $mediaModel }}" multiple>
            <flux:file-upload.dropzone
                heading="{{ $field->dropzoneHeading }}"
                text="{{ $field->dropzoneText }}"
                inline
            />
        </flux:file-upload>
    @else
        @unless ($hasSingleUpload)
            <flux:file-upload wire:model="{{ $mediaModel }}">
                <flux:file-upload.dropzone
                    heading="{{ $field->dropzoneHeading }}"
                    text="{{ $field->dropzoneText }}"
                    inline
                />
            </flux:file-upload>
        @endunless
    @endif

    <div
        class="mt-3 flex flex-col gap-2"
        x-data="{
            previewUrl: null,
            resolveFocusPoint(fallback, targetType, targetId) {
                const editor = this.$wire.activeMediaEditor;

                if (
                    editor &&
                    editor.fieldName === @js($field->name) &&
                    editor.editor === 'focus' &&
                    editor.targetType === targetType &&
                    Number(editor.targetId) === Number(targetId)
                ) {
                    return this.$wire.mediaEditorState ?? fallback;
                }

                return fallback;
            },
            resolveObjectPosition(focusPoint) {
                if (! @js($field->previewUsesFocusPoint)) {
                    return '50% 50%';
                }

                const x = Math.min(1, Math.max(0, Number(focusPoint?.x ?? 0.5)));
                const y = Math.min(1, Math.max(0, Number(focusPoint?.y ?? 0.5)));

                return `${x * 100}% ${y * 100}%`;
            },
            hasFocusPoint(focusPoint) {
                return focusPoint && typeof focusPoint === 'object';
            },
            resolveMarkerStyle(focusPoint) {
                const x = Math.min(1, Math.max(0, Number(focusPoint?.x ?? 0.5)));
                const y = Math.min(1, Math.max(0, Number(focusPoint?.y ?? 0.5)));

                return `left: ${x * 100}%; top: ${y * 100}%;`;
            },
        }"
    >
        @foreach ($visibleExistingItems as $media)
            @php
                $focusPoint = data_get($media, 'focusPoint');
                $existingPreviewObjectPosition = $resolvePreviewObjectPosition($focusPoint, $field->previewUsesFocusPoint);
            @endphp
            <div class="relative w-fit max-w-full" wire:key="existing-{{ $field->name }}-{{ $media['id'] }}">
                <div class="block cursor-pointer {{ $previewMaxWidthClasses }}" @click="previewUrl = '{{ $media['url'] }}'; $flux.modal('image-preview-{{ $field->name }}').show()">
                    @if ($previewAspectRatioClass)
                        <div class="relative overflow-hidden rounded-t-lg {{ $previewAspectRatioClass }}">
                            <img
                                src="{{ $media['url'] }}"
                                alt="{{ $media['name'] }}"
                                class="h-full w-full {{ $field->previewCropsImage ? 'object-cover' : 'object-contain' }}"
                                style="object-position: {{ $existingPreviewObjectPosition }};"
                                :style="`object-position: ${resolveObjectPosition(resolveFocusPoint(@js($focusPoint), 'existing', {{ $media['id'] }}))};`"
                            />
                            <div class="absolute top-2 z-10 flex items-center gap-1" style="right: 0.5rem; left: auto;" @click.stop>
                                @if ($hasEditors)
                                    <flux:button type="button" variant="ghost" size="xs" icon="pencil" wire:click="openExistingMediaEditor('{{ $field->name }}', {{ $media['id'] }})" />
                                @endif
                                <flux:button type="button" variant="ghost" size="xs" icon="trash-2" wire:click="removeExistingMedia('{{ $field->name }}', {{ $media['id'] }})" class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                            </div>
                        </div>
                    @else
                        <div class="relative">
                            <img src="{{ $media['url'] }}" alt="{{ $media['name'] }}" class="h-auto w-full rounded-t-lg object-contain" />
                            @if (is_array($focusPoint))
                                <div
                                    class="pointer-events-none absolute z-[11] h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-zinc-900/55 shadow"
                                    style="left: {{ ($focusPoint['x'] ?? 0.5) * 100 }}%; top: {{ ($focusPoint['y'] ?? 0.5) * 100 }}%;"
                                    x-show="hasFocusPoint(resolveFocusPoint(@js($focusPoint), 'existing', {{ $media['id'] }}))"
                                    :style="resolveMarkerStyle(resolveFocusPoint(@js($focusPoint), 'existing', {{ $media['id'] }}))"
                                ></div>
                            @endif
                            <div class="absolute top-2 z-10 flex items-center gap-1" style="right: 0.5rem; left: auto;" @click.stop>
                                @if ($hasEditors)
                                    <flux:button type="button" variant="ghost" size="xs" icon="pencil" wire:click="openExistingMediaEditor('{{ $field->name }}', {{ $media['id'] }})" />
                                @endif
                                <flux:button type="button" variant="ghost" size="xs" icon="trash-2" wire:click="removeExistingMedia('{{ $field->name }}', {{ $media['id'] }})" class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                            </div>
                        </div>
                    @endif
                </div>
                <div class="flex flex-col gap-1 rounded-b-lg bg-zinc-800/5 px-3 py-2 dark:bg-white/10">
                    <div class="break-all text-sm font-medium text-zinc-700 dark:text-white/80">{{ $media['name'] }}</div>
                    @if ($formatFileSize($media['size'] ?? null))
                        <div class="text-xs text-zinc-500 dark:text-white/60">{{ $formatFileSize($media['size']) }}</div>
                    @endif
                </div>
            </div>
        @endforeach

        @if (is_array($newUploads))
            @foreach ($newUploads as $index => $upload)
                @php
                    $uploadExists = $upload->exists();
                    $uploadName = $upload->getClientOriginalName();
                    $uploadSize = $uploadExists ? $upload->getSize() : null;
                    $uploadImage = ($uploadExists && $upload->isPreviewable()) ? $upload->temporaryUrl() : null;
                    $formattedUploadSize = $formatFileSize($uploadSize);
                    $draftKey = method_exists($this, 'mediaUploadDraftKey') ? $this->mediaUploadDraftKey($field->name, $index) : null;
                    $draftFocusPoint = $draftKey ? ($this->mediaEditorDrafts[$field->name][$draftKey]['focus'] ?? null) : null;
                    $uploadPreviewObjectPosition = $resolvePreviewObjectPosition($draftFocusPoint, $field->previewUsesFocusPoint);
                @endphp
                @if ($uploadImage)
                    <div class="relative w-fit max-w-full" wire:key="upload-{{ $field->name }}-{{ $index }}">
                        <div class="block cursor-pointer {{ $previewMaxWidthClasses }}" @click="previewUrl = '{{ $uploadImage }}'; $flux.modal('image-preview-{{ $field->name }}').show()">
                            @if ($previewAspectRatioClass)
                                <div class="relative overflow-hidden rounded-t-lg {{ $previewAspectRatioClass }}">
                                    <img
                                        src="{{ $uploadImage }}"
                                        alt="{{ $uploadName }}"
                                        class="h-full w-full {{ $field->previewCropsImage ? 'object-cover' : 'object-contain' }}"
                                        style="object-position: {{ $uploadPreviewObjectPosition }};"
                                        :style="`object-position: ${resolveObjectPosition(resolveFocusPoint(@js($draftFocusPoint), 'new', {{ $index }}))};`"
                                    />
                                    <div class="absolute top-2 z-10 flex items-center gap-1" style="right: 0.5rem; left: auto;" @click.stop>
                                        @if ($hasEditors)
                                            <flux:button type="button" variant="ghost" size="xs" icon="pencil" wire:click="openNewMediaEditor('{{ $field->name }}', {{ $index }})" />
                                        @endif
                                        <flux:button type="button" variant="ghost" size="xs" icon="trash-2" wire:click="removeNewUpload('{{ $field->name }}', {{ $index }})" class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                                    </div>
                                </div>
                            @else
                                <div class="relative">
                                    <img src="{{ $uploadImage }}" alt="{{ $uploadName }}" class="h-auto w-full rounded-t-lg object-contain" />
                                    @if (is_array($draftFocusPoint))
                                        <div
                                            class="pointer-events-none absolute z-[11] h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-zinc-900/55 shadow"
                                            style="left: {{ ($draftFocusPoint['x'] ?? 0.5) * 100 }}%; top: {{ ($draftFocusPoint['y'] ?? 0.5) * 100 }}%;"
                                            x-show="hasFocusPoint(resolveFocusPoint(@js($draftFocusPoint), 'new', {{ $index }}))"
                                            :style="resolveMarkerStyle(resolveFocusPoint(@js($draftFocusPoint), 'new', {{ $index }}))"
                                        ></div>
                                    @endif
                                    <div class="absolute top-2 z-10 flex items-center gap-1" style="right: 0.5rem; left: auto;" @click.stop>
                                        @if ($hasEditors)
                                            <flux:button type="button" variant="ghost" size="xs" icon="pencil" wire:click="openNewMediaEditor('{{ $field->name }}', {{ $index }})" />
                                        @endif
                                        <flux:button type="button" variant="ghost" size="xs" icon="trash-2" wire:click="removeNewUpload('{{ $field->name }}', {{ $index }})" class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="flex flex-col gap-1 rounded-b-lg bg-zinc-800/5 px-3 py-2 dark:bg-white/10">
                            <div class="break-all text-sm font-medium text-zinc-700 dark:text-white/80">{{ $uploadName }}</div>
                            @if ($formattedUploadSize)
                                <div class="text-xs text-zinc-500 dark:text-white/60">{{ $formattedUploadSize }}</div>
                            @endif
                        </div>
                    </div>
                @else
                    <flux:file-item
                        wire:key="upload-{{ $field->name }}-{{ $index }}"
                        :heading="$uploadName"
                        :size="$uploadSize"
                    >
                        <x-slot name="actions">
                            <flux:file-item.remove @click.stop wire:click="removeNewUpload('{{ $field->name }}', {{ $index }})" />
                        </x-slot>
                    </flux:file-item>
                @endif
            @endforeach
        @elseif ($newUploads)
            @php
                $uploadExists = $newUploads->exists();
                $uploadName = $newUploads->getClientOriginalName();
                $uploadSize = $uploadExists ? $newUploads->getSize() : null;
                $uploadImage = ($uploadExists && $newUploads->isPreviewable()) ? $newUploads->temporaryUrl() : null;
                $formattedUploadSize = $formatFileSize($uploadSize);
                $draftKey = method_exists($this, 'mediaUploadDraftKey') ? $this->mediaUploadDraftKey($field->name, 0) : null;
                $draftFocusPoint = $draftKey ? ($this->mediaEditorDrafts[$field->name][$draftKey]['focus'] ?? null) : null;
                $singleUploadPreviewObjectPosition = $resolvePreviewObjectPosition($draftFocusPoint, $field->previewUsesFocusPoint);
            @endphp
            @if ($uploadImage)
                <div class="relative w-fit max-w-full" wire:key="upload-{{ $field->name }}-single">
                    <div class="block cursor-pointer {{ $previewMaxWidthClasses }}" @click="previewUrl = '{{ $uploadImage }}'; $flux.modal('image-preview-{{ $field->name }}').show()">
                        @if ($previewAspectRatioClass)
                            <div class="relative overflow-hidden rounded-t-lg {{ $previewAspectRatioClass }}">
                                <img
                                    src="{{ $uploadImage }}"
                                    alt="{{ $uploadName }}"
                                    class="h-full w-full {{ $field->previewCropsImage ? 'object-cover' : 'object-contain' }}"
                                    style="object-position: {{ $singleUploadPreviewObjectPosition }};"
                                    :style="`object-position: ${resolveObjectPosition(resolveFocusPoint(@js($draftFocusPoint), 'new', 0))};`"
                                />
                                <div class="absolute top-2 z-10 flex items-center gap-1" style="right: 0.5rem; left: auto;" @click.stop>
                                    @if ($hasEditors)
                                        <flux:button type="button" variant="ghost" size="xs" icon="pencil" wire:click="openNewMediaEditor('{{ $field->name }}', 0)" />
                                    @endif
                                    <flux:button type="button" variant="ghost" size="xs" icon="trash-2" wire:click="removeNewUpload('{{ $field->name }}', 0)" class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                                </div>
                            </div>
                        @else
                            <div class="relative">
                                <img src="{{ $uploadImage }}" alt="{{ $uploadName }}" class="h-auto w-full rounded-t-lg object-contain" />
                                @if (is_array($draftFocusPoint))
                                    <div
                                        class="pointer-events-none absolute z-[11] h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-zinc-900/55 shadow"
                                        style="left: {{ ($draftFocusPoint['x'] ?? 0.5) * 100 }}%; top: {{ ($draftFocusPoint['y'] ?? 0.5) * 100 }}%;"
                                        x-show="hasFocusPoint(resolveFocusPoint(@js($draftFocusPoint), 'new', 0))"
                                        :style="resolveMarkerStyle(resolveFocusPoint(@js($draftFocusPoint), 'new', 0))"
                                    ></div>
                                @endif
                                <div class="absolute top-2 z-10 flex items-center gap-1" style="right: 0.5rem; left: auto;" @click.stop>
                                    @if ($hasEditors)
                                        <flux:button type="button" variant="ghost" size="xs" icon="pencil" wire:click="openNewMediaEditor('{{ $field->name }}', 0)" />
                                    @endif
                                    <flux:button type="button" variant="ghost" size="xs" icon="trash-2" wire:click="removeNewUpload('{{ $field->name }}', 0)" class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-col gap-1 rounded-b-lg bg-zinc-800/5 px-3 py-2 dark:bg-white/10">
                        <div class="break-all text-sm font-medium text-zinc-700 dark:text-white/80">{{ $uploadName }}</div>
                        @if ($formattedUploadSize)
                            <div class="text-xs text-zinc-500 dark:text-white/60">{{ $formattedUploadSize }}</div>
                        @endif
                    </div>
                </div>
            @else
                <flux:file-item
                    wire:key="upload-{{ $field->name }}-single"
                    :heading="$uploadName"
                    :size="$uploadSize"
                >
                    <x-slot name="actions">
                        <flux:file-item.remove @click.stop wire:click="removeNewUpload('{{ $field->name }}', 0)" />
                    </x-slot>
                </flux:file-item>
            @endif
        @endif

        <flux:modal :name="'image-preview-' . $field->name" variant="bare" class="w-auto max-w-[90vw] bg-transparent! shadow-none!">
            <div class="flex items-center justify-center" @click="$flux.modal('image-preview-{{ $field->name }}').close()">
                <img :src="previewUrl" @click.stop class="max-h-[85vh] max-w-[85vw] rounded-lg object-contain" />
            </div>
        </flux:modal>

        @php
            $currentMediaEditorField = $this->activeMediaEditor['fieldName'] ?? null;
            $currentMediaEditorView = method_exists($this, 'activeMediaEditorView')
                ? $this->activeMediaEditorView()
                : null;
        @endphp

        @if ($hasEditors)
            <flux:modal
                :name="method_exists($this, 'mediaEditorModalName') ? $this->mediaEditorModalName($field->name) : 'media-editor-' . $field->name"
                class="max-w-4xl"
                x-on:close="$wire.closeMediaEditor()"
            >
                <div class="flex max-h-[90vh] flex-col gap-4">
                    <div class="shrink-0">
                        <flux:heading size="lg">Set Crop Focus</flux:heading>
                    </div>

                    <div class="flex-1">
                        @if ($currentMediaEditorField === $field->name && $currentMediaEditorView)
                            @include($currentMediaEditorView, [
                                'context' => $this->activeMediaEditor,
                                'state' => $this->mediaEditorState,
                                'field' => $field,
                            ])
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center justify-end gap-2">
                        <flux:button type="button" variant="ghost" wire:click="cancelMediaEditor">Cancel</flux:button>
                        <flux:button type="button" variant="primary" wire:click="saveMediaEditor">Save</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    </div>
</x-form-kit::form.field-shell>
