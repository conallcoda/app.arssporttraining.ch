@php
    $bindingPrefix = $bindingPrefix ?? '';
    $state = $state ?? [];
    $showHrvDescription = $showHrvDescription ?? true;
    $emojiTickClass = 'flex w-14 sm:w-16 flex-col items-center gap-1 text-center';
    $emojiLabelClass = 'text-[10px] leading-tight sm:text-xs';
    $restingHeartRateField = $bindingPrefix . 'restingHeartRate';
    $hrvField = $bindingPrefix . 'hrv';
@endphp

<div class="space-y-10">
    <section class="space-y-6">
        <flux:heading size="lg">1. Sleep</flux:heading>

        <flux:field>
            <flux:label>How long did you sleep last night?</flux:label>
            <flux:slider wire:model.live.debounce.250ms="{{ $bindingPrefix }}sleepMinutes" min="360" max="510" step="5">
                <flux:slider.tick value="360">&lt;6:00</flux:slider.tick>
                <flux:slider.tick value="390">6:30</flux:slider.tick>
                <flux:slider.tick value="420">7:00</flux:slider.tick>
                <flux:slider.tick value="450">7:30</flux:slider.tick>
                <flux:slider.tick value="480">8:00</flux:slider.tick>
                <flux:slider.tick value="510">&gt;8:30</flux:slider.tick>
            </flux:slider>
            <flux:description>{{ $viewData['sleepDurationFormatted'] }} · {{ $viewData['sleepDurationLabel'] }}</flux:description>
        </flux:field>

        <flux:field>
            <flux:label>How well did you sleep?</flux:label>
            <flux:slider wire:model.live.debounce.250ms="{{ $bindingPrefix }}sleepQuality" min="1" max="5" step="1">
                <flux:slider.tick value="1"><span class="{{ $emojiTickClass }}"><flux:icon.angry class="size-6 sm:size-8 text-red-500" /><span class="{{ $emojiLabelClass }}">Very poor</span></span></flux:slider.tick>
                <flux:slider.tick value="2"><span class="{{ $emojiTickClass }}"><flux:icon.frown class="size-6 sm:size-8 text-orange-500" /><span class="{{ $emojiLabelClass }}">Restless</span></span></flux:slider.tick>
                <flux:slider.tick value="3"><span class="{{ $emojiTickClass }}"><flux:icon.meh class="size-6 sm:size-8 text-yellow-500" /><span class="{{ $emojiLabelClass }}">Average</span></span></flux:slider.tick>
                <flux:slider.tick value="4"><span class="{{ $emojiTickClass }}"><flux:icon.smile class="size-6 sm:size-8 text-lime-500" /><span class="{{ $emojiLabelClass }}">Good</span></span></flux:slider.tick>
                <flux:slider.tick value="5"><span class="{{ $emojiTickClass }}"><flux:icon.laugh class="size-6 sm:size-8 text-green-500" /><span class="{{ $emojiLabelClass }}">Deep</span></span></flux:slider.tick>
            </flux:slider>
        </flux:field>

        <flux:field>
            <flux:label>Sleeping altitude (meters)</flux:label>
            <flux:slider wire:model.live.debounce.250ms="{{ $bindingPrefix }}altitudeMeters" min="1500" max="3000" step="50">
                <flux:slider.tick value="1500">&lt;1500</flux:slider.tick>
                <flux:slider.tick value="1750">1750</flux:slider.tick>
                <flux:slider.tick value="2000">2000</flux:slider.tick>
                <flux:slider.tick value="2250">2250</flux:slider.tick>
                <flux:slider.tick value="2500">2500</flux:slider.tick>
                <flux:slider.tick value="2750">2750</flux:slider.tick>
                <flux:slider.tick value="3000">&gt;3000</flux:slider.tick>
            </flux:slider>
            <flux:description>{{ $viewData['altitudeFormatted'] }} m · {{ $viewData['altitudeLabel'] }}</flux:description>
        </flux:field>
    </section>

    <flux:separator />

    <section class="space-y-6">
        <flux:heading size="lg">2. How you feel</flux:heading>

        <flux:field>
            <flux:label>Physical condition</flux:label>
            <flux:slider wire:model.live.debounce.250ms="{{ $bindingPrefix }}condition" min="1" max="5" step="1">
                <flux:slider.tick value="1"><span class="{{ $emojiTickClass }}"><flux:icon.angry class="size-6 sm:size-8 text-red-500" /><span class="{{ $emojiLabelClass }}">Exhausted</span></span></flux:slider.tick>
                <flux:slider.tick value="2"><span class="{{ $emojiTickClass }}"><flux:icon.frown class="size-6 sm:size-8 text-orange-500" /><span class="{{ $emojiLabelClass }}">Tired</span></span></flux:slider.tick>
                <flux:slider.tick value="3"><span class="{{ $emojiTickClass }}"><flux:icon.meh class="size-6 sm:size-8 text-yellow-500" /><span class="{{ $emojiLabelClass }}">Normal</span></span></flux:slider.tick>
                <flux:slider.tick value="4"><span class="{{ $emojiTickClass }}"><flux:icon.smile class="size-6 sm:size-8 text-lime-500" /><span class="{{ $emojiLabelClass }}">Good</span></span></flux:slider.tick>
                <flux:slider.tick value="5"><span class="{{ $emojiTickClass }}"><flux:icon.laugh class="size-6 sm:size-8 text-green-500" /><span class="{{ $emojiLabelClass }}">Fresh</span></span></flux:slider.tick>
            </flux:slider>
        </flux:field>

        <flux:field>
            <flux:label>Mood</flux:label>
            <flux:slider wire:model.live.debounce.250ms="{{ $bindingPrefix }}mood" min="1" max="5" step="1">
                <flux:slider.tick value="1"><span class="{{ $emojiTickClass }}"><flux:icon.angry class="size-6 sm:size-8 text-red-500" /><span class="{{ $emojiLabelClass }}">Terrible</span></span></flux:slider.tick>
                <flux:slider.tick value="2"><span class="{{ $emojiTickClass }}"><flux:icon.frown class="size-6 sm:size-8 text-orange-500" /><span class="{{ $emojiLabelClass }}">Bad</span></span></flux:slider.tick>
                <flux:slider.tick value="3"><span class="{{ $emojiTickClass }}"><flux:icon.meh class="size-6 sm:size-8 text-yellow-500" /><span class="{{ $emojiLabelClass }}">OK</span></span></flux:slider.tick>
                <flux:slider.tick value="4"><span class="{{ $emojiTickClass }}"><flux:icon.smile class="size-6 sm:size-8 text-lime-500" /><span class="{{ $emojiLabelClass }}">Good</span></span></flux:slider.tick>
                <flux:slider.tick value="5"><span class="{{ $emojiTickClass }}"><flux:icon.laugh class="size-6 sm:size-8 text-green-500" /><span class="{{ $emojiLabelClass }}">Great</span></span></flux:slider.tick>
            </flux:slider>
        </flux:field>

        <flux:field>
            <flux:label>Motivation to train</flux:label>
            <flux:slider wire:model.live.debounce.250ms="{{ $bindingPrefix }}motivation" min="1" max="5" step="1">
                <flux:slider.tick value="1"><span class="{{ $emojiTickClass }}"><flux:icon.angry class="size-6 sm:size-8 text-red-500" /><span class="{{ $emojiLabelClass }}">None</span></span></flux:slider.tick>
                <flux:slider.tick value="2"><span class="{{ $emojiTickClass }}"><flux:icon.frown class="size-6 sm:size-8 text-orange-500" /><span class="{{ $emojiLabelClass }}">Low</span></span></flux:slider.tick>
                <flux:slider.tick value="3"><span class="{{ $emojiTickClass }}"><flux:icon.meh class="size-6 sm:size-8 text-yellow-500" /><span class="{{ $emojiLabelClass }}">OK</span></span></flux:slider.tick>
                <flux:slider.tick value="4"><span class="{{ $emojiTickClass }}"><flux:icon.smile class="size-6 sm:size-8 text-lime-500" /><span class="{{ $emojiLabelClass }}">High</span></span></flux:slider.tick>
                <flux:slider.tick value="5"><span class="{{ $emojiTickClass }}"><flux:icon.laugh class="size-6 sm:size-8 text-green-500" /><span class="{{ $emojiLabelClass }}">Very high</span></span></flux:slider.tick>
            </flux:slider>
        </flux:field>

        <flux:field>
            <flux:label>Soreness</flux:label>
            <flux:slider wire:model.live.debounce.250ms="{{ $bindingPrefix }}soreness" min="1" max="5" step="1">
                <flux:slider.tick value="1"><span class="{{ $emojiTickClass }}"><flux:icon.angry class="size-6 sm:size-8 text-red-500" /><span class="{{ $emojiLabelClass }}">Severe</span></span></flux:slider.tick>
                <flux:slider.tick value="2"><span class="{{ $emojiTickClass }}"><flux:icon.frown class="size-6 sm:size-8 text-orange-500" /><span class="{{ $emojiLabelClass }}">Heavy</span></span></flux:slider.tick>
                <flux:slider.tick value="3"><span class="{{ $emojiTickClass }}"><flux:icon.meh class="size-6 sm:size-8 text-yellow-500" /><span class="{{ $emojiLabelClass }}">Moderate</span></span></flux:slider.tick>
                <flux:slider.tick value="4"><span class="{{ $emojiTickClass }}"><flux:icon.smile class="size-6 sm:size-8 text-lime-500" /><span class="{{ $emojiLabelClass }}">Slight</span></span></flux:slider.tick>
                <flux:slider.tick value="5"><span class="{{ $emojiTickClass }}"><flux:icon.laugh class="size-6 sm:size-8 text-green-500" /><span class="{{ $emojiLabelClass }}">None</span></span></flux:slider.tick>
            </flux:slider>
        </flux:field>

        <flux:field>
            <flux:label>Energy</flux:label>
            <flux:slider wire:model.live.debounce.250ms="{{ $bindingPrefix }}energy" min="1" max="5" step="1">
                <flux:slider.tick value="1"><span class="{{ $emojiTickClass }}"><flux:icon.battery class="size-6 sm:size-8 text-red-500" /><span class="{{ $emojiLabelClass }}">Empty</span></span></flux:slider.tick>
                <flux:slider.tick value="2"><span class="{{ $emojiTickClass }}"><flux:icon.battery-low class="size-6 sm:size-8 text-orange-500" /><span class="{{ $emojiLabelClass }}">Low</span></span></flux:slider.tick>
                <flux:slider.tick value="3"><span class="{{ $emojiTickClass }}"><flux:icon.battery-medium class="size-6 sm:size-8 text-yellow-500" /><span class="{{ $emojiLabelClass }}">OK</span></span></flux:slider.tick>
                <flux:slider.tick value="4"><span class="{{ $emojiTickClass }}"><flux:icon.battery-full class="size-6 sm:size-8 text-lime-500" /><span class="{{ $emojiLabelClass }}">Good</span></span></flux:slider.tick>
                <flux:slider.tick value="5"><span class="{{ $emojiTickClass }}"><flux:icon.battery-charging class="size-6 sm:size-8 text-green-500" /><span class="{{ $emojiLabelClass }}">Full</span></span></flux:slider.tick>
            </flux:slider>
        </flux:field>
    </section>

    <flux:separator />

    <section class="space-y-6">
        <flux:heading size="lg">3. Vitals</flux:heading>

        <flux:field>
            <flux:label>Resting heart rate (bpm)</flux:label>
            <flux:input.group>
                <flux:input
                    type="number"
                    wire:model.live.debounce.250ms="{{ $bindingPrefix }}restingHeartRate"
                    min="30"
                    max="200"
                    required
                    class="w-full"
                />
                <flux:input.group.suffix>bpm</flux:input.group.suffix>
            </flux:input.group>
            @error($restingHeartRateField)
                <flux:text class="text-sm text-red-500">{{ $message }}</flux:text>
            @enderror
        </flux:field>

        @if (($showRhrSummary ?? true) === true)
            <flux:text class="text-sm">
                RHR delta: {{ $viewData['rhrDelta'] ?? '—' }} bpm · score {{ $viewData['rhrScore'] ?? '—' }}/5
            </flux:text>
        @endif

        <flux:field>
            <flux:label>Heart Rate Variability (HRV)</flux:label>
            <flux:input.group>
                <flux:input
                    type="number"
                    wire:model.live.debounce.250ms="{{ $bindingPrefix }}hrv"
                    min="0"
                    required
                    class="w-full"
                />
                <flux:input.group.suffix>ms</flux:input.group.suffix>
            </flux:input.group>
            @error($hrvField)
                <flux:text class="text-sm text-red-500">{{ $message }}</flux:text>
            @enderror
        </flux:field>
    </section>
</div>
