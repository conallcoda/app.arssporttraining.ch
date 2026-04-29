@php
    $bindingPrefix = $bindingPrefix ?? '';
    $showAdminDetails = $showAdminDetails ?? false;
    $showBaselineField = $showBaselineField ?? false;
    $showExtremeOffsetField = $showExtremeOffsetField ?? false;
@endphp

<div class="space-y-6">
    @if ($showBaselineField)
        <flux:card class="space-y-6">
            <flux:field>
                <flux:label>Baseline RHR (bpm)</flux:label>
                <flux:input
                    type="number"
                    wire:model.live.debounce.250ms="{{ $bindingPrefix }}restingHeartRateBaseline"
                    min="30"
                    max="200"
                    class="max-w-[120px]"
                />
                <flux:description>Normally averaged from the last 7 readiness submissions.</flux:description>
            </flux:field>

            @if ($showExtremeOffsetField)
                <flux:field>
                    <flux:label>Extreme offset</flux:label>
                    <flux:input
                        type="number"
                        wire:model.live.debounce.250ms="extremeOffset"
                        min="-5"
                        max="1"
                        step="1"
                        class="max-w-[120px]"
                    />
                    <flux:description>Scores of 1 are substituted with this value.</flux:description>
                </flux:field>
            @endif
        </flux:card>
    @endif

    @include('livewire.readiness.partials.breakdown-card', [
        'viewData' => $viewData,
        'showAdminDetails' => $showAdminDetails,
    ])
</div>
