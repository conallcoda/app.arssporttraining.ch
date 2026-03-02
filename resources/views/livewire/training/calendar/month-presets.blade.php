<div class="space-y-3">
    <div class="flex flex-wrap gap-1.5">
        @foreach ($this->monthPresets as $preset)
            @if ($preset['active'] && !$this->pendingOther)
                <flux:button
                    wire:click="selectMonth('{{ $preset['value'] }}')"
                    variant="primary"
                    size="sm"
                >
                    {{ $preset['label'] }}
                </flux:button>
            @else
                <flux:button
                    wire:click="selectMonth('{{ $preset['value'] }}')"
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
        <flux:date-picker wire:model.live="otherMonthDate" week-numbers with-today />
    @endif
</div>
