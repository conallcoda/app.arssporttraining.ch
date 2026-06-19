@props([
    'src' => null,
    'srcset' => null,
    'sizes' => null,
    'name' => '',
    'shape' => 'rounded',
    'size' => 'sm',
    'width' => null,
    'height' => null,
    'objectPosition' => '50% 50%',
    'showInitialsFallback' => true,
])

@php
    $sizeClasses = match ($size) {
        'xs' => 'h-8 w-8 text-xs',
        'sm' => 'h-10 w-10 text-sm',
        'md' => 'h-12 w-12 text-base',
        'lg' => 'h-16 w-16 text-lg',
        default => 'h-10 w-10 text-sm',
    };

    $shapeClasses = match ($shape) {
        'square' => 'rounded-none',
        'circle' => 'rounded-full',
        default => 'rounded-md',
    };

    $fluxFallbackSize = match ($size) {
        'xs' => 'sm',
        'sm' => 'md',
        'md' => 'lg',
        'lg' => 'xl',
        default => 'md',
    };

    $initials = collect(preg_split('/\s+/', trim((string) $name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part) => strtoupper(mb_substr($part, 0, 1)))
        ->join('');

@endphp

@if (is_string($src) && $src !== '')
    <div class="{{ $sizeClasses }} {{ $shapeClasses }} shrink-0 overflow-hidden bg-zinc-100 dark:bg-zinc-800">
        <img
            src="{{ $src }}"
            @if (is_string($srcset) && $srcset !== '') srcset="{{ $srcset }}" @endif
            @if (is_string($sizes) && $sizes !== '') sizes="{{ $sizes }}" @endif
            alt="{{ $name }}"
            @if (is_numeric($width)) width="{{ $width }}" @endif
            @if (is_numeric($height)) height="{{ $height }}" @endif
            class="h-full w-full object-cover"
            style="object-position: {{ $objectPosition }};"
        />
    </div>
@else
    <div class="{{ $sizeClasses }} {{ $shapeClasses }} shrink-0 overflow-hidden">
        <flux:avatar
            :src="null"
            :name="$name"
            :icon="$showInitialsFallback ? null : 'image'"
            :initials="$showInitialsFallback && $initials !== '' ? $initials : null"
            :size="$fluxFallbackSize"
            :no-initials-fallback="! $showInitialsFallback"
            class="h-full w-full"
        />
    </div>
@endif
