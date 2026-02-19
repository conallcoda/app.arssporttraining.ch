@props([
    'meta' => null,
    'value' => '-',
    'size' => 'sm',
    'type' => null,
])

<input x-show="editing" x-cloak x-ref="input" x-model="editValue"
    @if ($meta)
        type="{{ $type ?? $meta->inputType }}"
        @if ($meta->inputType === 'number')
            step="{{ $meta->inputStep }}"
        @endif
        @if ($meta->min !== null)
            min="{{ $meta->min }}"
        @endif
        @if ($meta->max !== null)
            max="{{ $meta->max }}"
        @endif
        @if ($meta->maxlength !== null)
            maxlength="{{ $meta->maxlength }}"
        @endif
        @if ($meta->pattern !== null)
            pattern="{{ $meta->pattern }}"
        @endif
    @else
        type="{{ $type ?? 'number' }}"
    @endif
    class="w-full h-full px-2 py-1.5 text-center text-{{ $size }} bg-transparent border-0 focus:ring-0"
    placeholder="{{ $value }}"
    @blur="commitEdit()"
    @keydown="handleKeydown($event)"
    @input="applyMask($event)" />
