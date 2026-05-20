@php
    $isMapped = $field->isMapped();
    $currentValue = $isMapped ? null : data_get($this, $wireModel);
    $anyPresetActive = $isMapped
        ? collect($field->presets)->contains(fn($p) => collect($p['values'])->every(fn($v, $k) => data_get($this, "{$prefix}.{$k}") === $v))
        : collect($field->presets)->contains(fn($p) => $p['value'] === $currentValue);
@endphp

<div {{ $attributes }} x-data="{ showOther: {{ !$anyPresetActive && $field->hasOther() ? 'true' : 'false' }} }">
    <div class="flex flex-wrap gap-1.5">
        @foreach ($field->presets as $preset)
            @if ($isMapped)
                @php
                    $setCommands = collect($preset['values'])
                        ->map(fn($val, $key) => "\$wire.set('{$prefix}.{$key}', '{$val}')")
                        ->implode('; ');
                    $isActive = collect($preset['values'])
                        ->every(fn($val, $key) => data_get($this, "{$prefix}.{$key}") === $val);
                @endphp
                @if ($isActive)
                    <span class="inline-flex rounded-lg" x-bind:class="!showOther && 'bg-zinc-200 dark:bg-zinc-700'">
                        <flux:button
                            x-on:click="{{ $setCommands }}; showOther = false"
                            variant="ghost"
                            size="sm"
                        >
                            {{ $preset['label'] }}
                        </flux:button>
                    </span>
                @else
                    <flux:button
                        x-on:click="{{ $setCommands }}; showOther = false"
                        variant="ghost"
                        size="sm"
                    >
                        {{ $preset['label'] }}
                    </flux:button>
                @endif
            @else
                @if ($preset['value'] === $currentValue)
                    <span class="inline-flex rounded-lg" x-bind:class="!showOther && 'bg-zinc-200 dark:bg-zinc-700'">
                        <flux:button
                            wire:click="$set('{{ $wireModel }}', '{{ $preset['value'] }}')"
                            x-on:click="showOther = false"
                            variant="ghost"
                            size="sm"
                        >
                            {{ $preset['label'] }}
                        </flux:button>
                    </span>
                @else
                    <flux:button
                        wire:click="$set('{{ $wireModel }}', '{{ $preset['value'] }}')"
                        x-on:click="showOther = false"
                        variant="ghost"
                        size="sm"
                    >
                        {{ $preset['label'] }}
                    </flux:button>
                @endif
            @endif
        @endforeach
        @if ($field->hasOther())
            <span class="inline-flex rounded-lg" x-bind:class="showOther && 'bg-zinc-200 dark:bg-zinc-700'">
                <flux:button
                    x-on:click="showOther = !showOther"
                    variant="ghost"
                    size="sm"
                >
                    Other
                </flux:button>
            </span>
        @endif
    </div>
    @if ($field->otherField)
        <div x-show="showOther" x-cloak class="mt-3">
            <x-form-kit::form.field :field="$field->otherField" :prefix="$prefix" />
        </div>
    @elseif (! empty($field->otherFields))
        <div x-show="showOther" x-cloak class="mt-3 flex items-end gap-3">
            @foreach ($field->otherFields as $otherField)
                <x-form-kit::form.field :field="$otherField" :prefix="$prefix" class="flex-1" />
            @endforeach
        </div>
    @endif
</div>
