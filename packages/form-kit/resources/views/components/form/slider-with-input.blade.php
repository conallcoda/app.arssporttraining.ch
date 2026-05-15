@props([
    'label',
    'model',
    'min',
    'max',
    'step',
    'suffix' => null,
    'ticks' => [],
    'inputMin' => null,
    'inputMax' => null,
    'required' => false,
])

@php
    $inputMin = $inputMin ?? $min;
    $inputMax = $inputMax ?? $max;
@endphp

<flux:field>
    <flux:label :badge="$required ? 'Required' : null">{{ $label }}</flux:label>
    <div class="flex items-center gap-4 -mt-2">
        <flux:slider :wire:model.live="$model" :min="$min" :max="$max" :step="$step">
            @foreach ($ticks as $tick)
                <flux:slider.tick :value="$tick">{{ $tick }}</flux:slider.tick>
            @endforeach
        </flux:slider>
        @if ($suffix)
            <flux:input.group>
                <flux:input :wire:model.live="$model" type="number" size="sm" class="max-w-18"
                    :min="$inputMin" :max="$inputMax" :step="$step" />
                <flux:input.group.suffix>{{ $suffix }}</flux:input.group.suffix>
            </flux:input.group>
        @else
            <flux:input :wire:model.live="$model" type="number" size="sm" class="max-w-18"
                :min="$inputMin" :max="$inputMax" :step="$step" />
        @endif
    </div>
</flux:field>
