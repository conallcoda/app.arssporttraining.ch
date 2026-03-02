<div class="space-y-3">
    <div class="flex flex-wrap gap-1.5">
        @foreach ($this->rangePresets as $preset)
            @if ($preset['active'] && !$this->pendingOther)
                <flux:button
                    wire:click="selectRange('{{ $preset['start'] }}', '{{ $preset['end'] }}')"
                    variant="primary"
                    size="sm"
                >
                    {{ $preset['label'] }}
                </flux:button>
            @else
                <flux:button
                    wire:click="selectRange('{{ $preset['start'] }}', '{{ $preset['end'] }}')"
                    variant="ghost"
                    size="sm"
                >
                    {{ $preset['label'] }}
                </flux:button>
            @endif
        @endforeach
        @if ($this->pendingOther)
            <flux:button wire:click="toggleOther" variant="primary" size="sm">
                Other
            </flux:button>
        @else
            <flux:button wire:click="toggleOther" variant="ghost" size="sm">
                Other
            </flux:button>
        @endif
    </div>

    @if ($this->pendingOther)
        <div class="flex items-end gap-3">
            <flux:field class="flex-1">
                <flux:label>Start</flux:label>
                <flux:date-picker wire:model.live="pendingStart" week-numbers with-today />
            </flux:field>
            <flux:field class="flex-1">
                <flux:label>End</flux:label>
                <flux:date-picker wire:model.live="pendingEnd" week-numbers with-today />
            </flux:field>
        </div>
    @endif
</div>
