@php
    $definition = $definition ?? null;
    $record = $record ?? null;
    $footerView = $footerView ?? null;
    $footerData = $footerData ?? [];
    $cardClass = $cardClass ?? '!p-0 overflow-hidden flex flex-col';
    $bodyClass = $bodyClass ?? 'p-4 flex-1 flex flex-col gap-3';
    $imageField = $definition?->imageField;
    $titleField = $definition?->titleField;
    $subtitleField = $definition?->subtitleField;
    $metaFields = $definition?->metaFields ?? [];
    $bodyFields = $definition?->bodyFields ?? [];
    $badgeFields = $definition?->badgeFields ?? [];
    $infoView = $definition?->infoView;
    $customView = $definition?->view;
    $legacyCards = $cards ?? [];
    $cardUrl = $cardUrl ?? null;
    $lightboxEnabled = (bool) ($lightboxEnabled ?? false);
    $lightboxIndex = (int) ($lightboxIndex ?? 0);
    $wireKey = $wireKey ?? null;
    $resolvedWireKey = $wireKey ?? '';
@endphp

@if ($customView)
    @include($customView, [
        'definition' => $definition,
        'record' => $record,
        'item' => $item ?? $record,
        'model' => $model ?? $record,
        'titleField' => $titleField,
        'subtitleField' => $subtitleField,
        'bodyFields' => $bodyFields,
        'metaFields' => $metaFields,
        'badgeFields' => $badgeFields,
        'imageField' => $imageField,
        'cards' => $legacyCards,
    ])
@else
    <flux:card wire:key="{{ $resolvedWireKey }}" class="{{ $cardClass }}">
        @if ($imageField)
            @php
                $imageUrl = $imageField->resolveValue($record);
                $aspect = data_get($imageField, 'aspect', 'square');
                $fit = data_get($imageField, 'fit', 'cover');
                $insetImage = (bool) data_get($imageField, 'insetImage', false);
                $imageInsetClass = (string) data_get($imageField, 'imageInsetClass', 'p-4');
                $imageContainerClass = data_get($imageField, 'imageContainerClass');
                $aspectClass = match ($aspect) {
                    'video' => 'aspect-video',
                    'auto' => '',
                    default => 'aspect-square',
                };
                $fitClass = $fit === 'contain' ? 'object-contain' : 'object-cover';
                $resolvedImageContainerClass = is_string($imageContainerClass) && $imageContainerClass !== ''
                    ? $imageContainerClass
                    : 'w-full '.$aspectClass.' bg-zinc-100 dark:bg-zinc-800 overflow-hidden';
            @endphp
            <div class="group/image relative {{ $resolvedImageContainerClass }}">
                @if ($imageUrl)
                    @if ($insetImage)
                        <div class="w-full h-full {{ $imageInsetClass }} flex items-center justify-center">
                            <img src="{{ $imageUrl }}" alt="" class="w-full h-full {{ $fitClass }} transition-transform duration-200 ease-out group-hover/image:scale-105" loading="lazy" />
                        </div>
                    @else
                        <img src="{{ $imageUrl }}" alt="" class="w-full h-full {{ $fitClass }} transition-transform duration-200 ease-out group-hover/image:scale-105" loading="lazy" />
                    @endif

                    @if ($lightboxEnabled)
                        <button type="button"
                            class="absolute inset-0 z-[1] flex items-center justify-center bg-black/0 text-white opacity-0 transition-opacity duration-150 group-hover/image:opacity-100 cursor-zoom-in"
                            aria-label="Open image lightbox"
                            x-on:click.stop="$dispatch('open-lightbox', { index: {{ $lightboxIndex }} })">
                            <span class="flex size-10 items-center justify-center rounded-full bg-black/55 shadow-sm">
                                <flux:icon.zoom-in class="size-5" />
                            </span>
                        </button>
                    @endif
                @else
                    <div class="w-full h-full flex items-center justify-center text-zinc-400 dark:text-zinc-600">
                        <flux:icon.photo class="size-10" />
                    </div>
                @endif
            </div>
        @endif

        <div class="{{ $bodyClass }}">
            @if ($infoView)
                @include($infoView, [
                    'definition' => $definition,
                    'record' => $record,
                    'item' => $item ?? $record,
                    'model' => $model ?? $record,
                    'titleField' => $titleField,
                    'subtitleField' => $subtitleField,
                    'bodyFields' => $bodyFields,
                    'metaFields' => $metaFields,
                    'badgeFields' => $badgeFields,
                    'imageField' => $imageField,
                ])
            @else
                @if ($badgeFields !== [])
                    <div class="flex flex-wrap gap-2">
                        @foreach ($badgeFields as $field)
                            @include('cms::partials.card-field', ['field' => $field, 'record' => $record])
                        @endforeach
                    </div>
                @endif

                @if ($titleField || $subtitleField)
                    <div class="space-y-1">
                        @if ($titleField)
                            <div class="text-base font-medium leading-tight">
                                @if ($cardUrl)
                                    <a href="{{ $cardUrl }}" class="hover:underline underline-offset-2" wire:navigate>
                                        @include('cms::partials.card-field', ['field' => $titleField, 'record' => $record])
                                    </a>
                                @else
                                    @include('cms::partials.card-field', ['field' => $titleField, 'record' => $record])
                                @endif
                            </div>
                        @endif
                        @if ($subtitleField)
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                @include('cms::partials.card-field', ['field' => $subtitleField, 'record' => $record])
                            </div>
                        @endif
                    </div>
                @endif

                @if ($metaFields !== [])
                    <div class="flex flex-wrap gap-x-4 gap-y-1">
                        @foreach ($metaFields as $field)
                            <div class="flex items-center gap-1.5">
                                @if ($field->showLabel)
                                    <flux:text size="sm" variant="subtle">{{ $field->resolvedLabel() }}</flux:text>
                                @endif
                                @include('cms::partials.card-field', ['field' => $field, 'record' => $record])
                            </div>
                        @endforeach
                    </div>
                @endif

                @foreach ($bodyFields as $field)
                    <div>
                        @if ($field->showLabel)
                            <flux:text size="xs" variant="subtle" class="uppercase tracking-wide">{{ $field->resolvedLabel() }}</flux:text>
                        @endif
                        <div class="{{ $field->showLabel ? 'mt-0.5' : '' }}">
                            @include('cms::partials.card-field', ['field' => $field, 'record' => $record])
                        </div>
                    </div>
                @endforeach
            @endif

            @if ($footerView)
                @include($footerView, $footerData)
            @endif
        </div>
        </flux:card>
@endif
