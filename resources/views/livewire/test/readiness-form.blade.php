@php
    $trafficLight = $this->trafficLight;
    $trafficLightMeta = [
        'ready' => ['label' => 'Ready', 'classes' => 'bg-green-500/15 text-green-700 dark:text-green-400 border-green-500/30'],
        'train_smart' => ['label' => 'Train Smart', 'classes' => 'bg-yellow-500/15 text-yellow-700 dark:text-yellow-400 border-yellow-500/30'],
        'recovery' => ['label' => 'Recovery', 'classes' => 'bg-orange-500/15 text-orange-700 dark:text-orange-400 border-orange-500/30'],
        'rest' => ['label' => 'Rest', 'classes' => 'bg-red-500/15 text-red-700 dark:text-red-400 border-red-500/30'],
    ];
@endphp

<flux:main>
    <div class="grid gap-8 lg:grid-cols-[1fr_340px]">
        <div class="space-y-8">
            <div>
                <flux:heading size="xl">Readiness</flux:heading>
                <flux:text class="mt-1">Daily check-in before training.</flux:text>
            </div>

            <flux:card class="space-y-6">
                <flux:heading size="lg">Sleep</flux:heading>

                <flux:field>
                    <flux:label>How long did you sleep last night?</flux:label>
                    <flux:input
                        wire:model.live.debounce.400ms="sleepDuration"
                        placeholder="7:45"
                        class="max-w-[140px]"
                    />
                    <flux:description>Format HH:MM. Duration score: {{ $this->sleepDurationScore ?? '—' }}/5</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>How well did you sleep?</flux:label>
                    <flux:slider wire:model.live="sleepQuality" min="1" max="5" step="1">
                        <flux:slider.tick value="1">Very poor</flux:slider.tick>
                        <flux:slider.tick value="2">Restless</flux:slider.tick>
                        <flux:slider.tick value="3">Average</flux:slider.tick>
                        <flux:slider.tick value="4">Good</flux:slider.tick>
                        <flux:slider.tick value="5">Deep</flux:slider.tick>
                    </flux:slider>
                </flux:field>

                <flux:field>
                    <flux:label>Sleeping altitude (meters)</flux:label>
                    <flux:input
                        type="number"
                        wire:model.live.debounce.400ms="altitudeMeters"
                        min="0"
                        step="50"
                        class="max-w-[160px]"
                    />
                    <flux:description>Altitude score: {{ $this->altitudeScore ?? '—' }}/5</flux:description>
                </flux:field>
            </flux:card>

            <flux:card class="space-y-6">
                <flux:heading size="lg">How you feel</flux:heading>

                <flux:field>
                    <flux:label>Physical condition</flux:label>
                    <flux:slider wire:model.live="condition" min="1" max="5" step="1">
                        <flux:slider.tick value="1">Exhausted</flux:slider.tick>
                        <flux:slider.tick value="2">Tired</flux:slider.tick>
                        <flux:slider.tick value="3">Normal</flux:slider.tick>
                        <flux:slider.tick value="4">Good</flux:slider.tick>
                        <flux:slider.tick value="5">Fresh</flux:slider.tick>
                    </flux:slider>
                </flux:field>

                <flux:field>
                    <flux:label>Mood</flux:label>
                    <flux:slider wire:model.live="mood" min="1" max="5" step="1">
                        <flux:slider.tick value="1">😫</flux:slider.tick>
                        <flux:slider.tick value="2">😕</flux:slider.tick>
                        <flux:slider.tick value="3">😐</flux:slider.tick>
                        <flux:slider.tick value="4">🙂</flux:slider.tick>
                        <flux:slider.tick value="5">😄</flux:slider.tick>
                    </flux:slider>
                </flux:field>

                <flux:field>
                    <flux:label>Motivation to train</flux:label>
                    <flux:slider wire:model.live="motivation" min="1" max="5" step="1">
                        <flux:slider.tick value="1">😫</flux:slider.tick>
                        <flux:slider.tick value="2">😕</flux:slider.tick>
                        <flux:slider.tick value="3">😐</flux:slider.tick>
                        <flux:slider.tick value="4">🙂</flux:slider.tick>
                        <flux:slider.tick value="5">😄</flux:slider.tick>
                    </flux:slider>
                </flux:field>

                <flux:field>
                    <flux:label>Muscle soreness</flux:label>
                    <flux:slider wire:model.live="soreness" min="1" max="5" step="1">
                        <flux:slider.tick value="1">None</flux:slider.tick>
                        <flux:slider.tick value="2">Slight</flux:slider.tick>
                        <flux:slider.tick value="3">Moderate</flux:slider.tick>
                        <flux:slider.tick value="4">Sore</flux:slider.tick>
                        <flux:slider.tick value="5">Very sore</flux:slider.tick>
                    </flux:slider>
                    <flux:description>Scored internally as {{ $this->sorenessScore ?? '—' }}/5 (inverted).</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Energy</flux:label>
                    <flux:slider wire:model.live="energy" min="1" max="5" step="1">
                        <flux:slider.tick value="1">Empty</flux:slider.tick>
                        <flux:slider.tick value="2">Low</flux:slider.tick>
                        <flux:slider.tick value="3">OK</flux:slider.tick>
                        <flux:slider.tick value="4">Good</flux:slider.tick>
                        <flux:slider.tick value="5">Full</flux:slider.tick>
                    </flux:slider>
                </flux:field>
            </flux:card>

            <flux:card class="space-y-6">
                <flux:heading size="lg">Vitals</flux:heading>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Resting heart rate (bpm)</flux:label>
                        <flux:input
                            type="number"
                            wire:model.live.debounce.400ms="restingHeartRate"
                            min="30"
                            max="200"
                        />
                    </flux:field>

                    <flux:field>
                        <flux:label>Baseline RHR (bpm)</flux:label>
                        <flux:input
                            type="number"
                            wire:model.live.debounce.400ms="restingHeartRateBaseline"
                            min="30"
                            max="200"
                        />
                        <flux:description>Test-only: normally averaged from last 7 days.</flux:description>
                    </flux:field>
                </div>

                <flux:text class="text-sm">
                    RHR delta: {{ $restingHeartRate - $restingHeartRateBaseline }} bpm · score {{ $this->rhrScore ?? '—' }}/5
                </flux:text>

                <flux:field>
                    <flux:label>HRV</flux:label>
                    <flux:input
                        type="number"
                        wire:model.live.debounce.400ms="hrv"
                        min="0"
                        class="max-w-[160px]"
                    />
                    <flux:description>Stored for trend analysis only — not used in readiness score.</flux:description>
                </flux:field>
            </flux:card>
        </div>

        <aside class="lg:sticky lg:top-6 lg:self-start">
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Today's readiness</flux:heading>
                    <flux:text class="mt-1 text-sm">Updates live as you fill the form.</flux:text>
                </div>

                <div class="flex items-baseline gap-2">
                    <span class="text-5xl font-semibold tabular-nums">
                        {{ $this->readinessScore !== null ? number_format($this->readinessScore, 2) : '—' }}
                    </span>
                    <span class="text-zinc-500">/ 5</span>
                </div>

                @if ($trafficLight)
                    <div class="inline-flex rounded-full border px-3 py-1 text-sm font-medium {{ $trafficLightMeta[$trafficLight]['classes'] }}">
                        {{ $trafficLightMeta[$trafficLight]['label'] }}
                    </div>
                @endif

                <flux:separator />

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Sleep</dt>
                        <dd class="tabular-nums">{{ $this->sleepScore !== null ? number_format($this->sleepScore, 2) : '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Condition</dt>
                        <dd class="tabular-nums">{{ $condition }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Mood</dt>
                        <dd class="tabular-nums">{{ $mood }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Motivation</dt>
                        <dd class="tabular-nums">{{ $motivation }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Soreness</dt>
                        <dd class="tabular-nums">{{ $this->sorenessScore ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Energy</dt>
                        <dd class="tabular-nums">{{ $energy }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">RHR</dt>
                        <dd class="tabular-nums">{{ $this->rhrScore ?? '—' }}</dd>
                    </div>
                </dl>
            </flux:card>
        </aside>
    </div>
</flux:main>
