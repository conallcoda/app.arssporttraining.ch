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
    <style>
        [data-flux-slider-tick]:first-child {
            translate: -10px 0 !important;
            align-items: flex-start !important;
            min-width: 0 !important;
        }
    </style>

    <div class="grid gap-8 lg:grid-cols-[1fr_340px]">
        <div class="space-y-8">
            <flux:card class="space-y-6">
                <flux:heading size="lg">Sleep</flux:heading>

                <flux:field>
                    <flux:label>How long did you sleep last night?</flux:label>
                    <flux:slider wire:model.live.debounce.250ms="sleepMinutes" min="360" max="510" step="5">
                        <flux:slider.tick value="360">&lt;6:00</flux:slider.tick>
                        <flux:slider.tick value="390">6:30</flux:slider.tick>
                        <flux:slider.tick value="420">7:00</flux:slider.tick>
                        <flux:slider.tick value="450">7:30</flux:slider.tick>
                        <flux:slider.tick value="480">8:00</flux:slider.tick>
                        <flux:slider.tick value="510">&gt;8:30</flux:slider.tick>
                    </flux:slider>
                    <flux:description>{{ $this->sleepDurationFormatted }} · {{ $this->sleepDurationLabel }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>How well did you sleep?</flux:label>
                    <flux:slider wire:model.live.debounce.250ms="sleepQuality" min="1" max="5" step="1">
                        <flux:slider.tick value="1"><span class="flex flex-col items-start gap-1"><flux:icon.angry class="size-8 text-red-500" /><span class="text-xs">Very poor</span></span></flux:slider.tick>
                        <flux:slider.tick value="2"><span class="flex flex-col items-center gap-1"><flux:icon.frown class="size-8 text-orange-500" /><span class="text-xs">Restless</span></span></flux:slider.tick>
                        <flux:slider.tick value="3"><span class="flex flex-col items-center gap-1"><flux:icon.meh class="size-8 text-yellow-500" /><span class="text-xs">Average</span></span></flux:slider.tick>
                        <flux:slider.tick value="4"><span class="flex flex-col items-center gap-1"><flux:icon.smile class="size-8 text-lime-500" /><span class="text-xs">Good</span></span></flux:slider.tick>
                        <flux:slider.tick value="5"><span class="flex flex-col items-center gap-1"><flux:icon.laugh class="size-8 text-green-500" /><span class="text-xs">Deep</span></span></flux:slider.tick>
                    </flux:slider>
                </flux:field>

                <flux:field>
                    <flux:label>Sleeping altitude (meters)</flux:label>
                    <flux:slider wire:model.live.debounce.250ms="altitudeMeters" min="1500" max="3000" step="50">
                        <flux:slider.tick value="1500">&lt;1500</flux:slider.tick>
                        <flux:slider.tick value="1750">1750</flux:slider.tick>
                        <flux:slider.tick value="2000">2000</flux:slider.tick>
                        <flux:slider.tick value="2250">2250</flux:slider.tick>
                        <flux:slider.tick value="2500">2500</flux:slider.tick>
                        <flux:slider.tick value="2750">2750</flux:slider.tick>
                        <flux:slider.tick value="3000">&gt;3000</flux:slider.tick>
                    </flux:slider>
                    <flux:description>{{ $this->altitudeFormatted }} m · {{ $this->altitudeLabel }}</flux:description>
                </flux:field>
            </flux:card>

            <flux:card class="space-y-6">
                <flux:heading size="lg">How you feel</flux:heading>

                <flux:field>
                    <flux:label>Physical condition</flux:label>
                    <flux:slider wire:model.live.debounce.250ms="condition" min="1" max="5" step="1">
                        <flux:slider.tick value="1"><span class="flex flex-col items-start gap-1"><flux:icon.angry class="size-8 text-red-500" /><span class="text-xs">Exhausted</span></span></flux:slider.tick>
                        <flux:slider.tick value="2"><span class="flex flex-col items-center gap-1"><flux:icon.frown class="size-8 text-orange-500" /><span class="text-xs">Tired</span></span></flux:slider.tick>
                        <flux:slider.tick value="3"><span class="flex flex-col items-center gap-1"><flux:icon.meh class="size-8 text-yellow-500" /><span class="text-xs">Normal</span></span></flux:slider.tick>
                        <flux:slider.tick value="4"><span class="flex flex-col items-center gap-1"><flux:icon.smile class="size-8 text-lime-500" /><span class="text-xs">Good</span></span></flux:slider.tick>
                        <flux:slider.tick value="5"><span class="flex flex-col items-center gap-1"><flux:icon.laugh class="size-8 text-green-500" /><span class="text-xs">Fresh</span></span></flux:slider.tick>
                    </flux:slider>
                </flux:field>

                <flux:field>
                    <flux:label>Mood</flux:label>
                    <flux:slider wire:model.live.debounce.250ms="mood" min="1" max="5" step="1">
                        <flux:slider.tick value="1"><span class="flex flex-col items-start gap-1"><flux:icon.angry class="size-8 text-red-500" /><span class="text-xs">Terrible</span></span></flux:slider.tick>
                        <flux:slider.tick value="2"><span class="flex flex-col items-center gap-1"><flux:icon.frown class="size-8 text-orange-500" /><span class="text-xs">Bad</span></span></flux:slider.tick>
                        <flux:slider.tick value="3"><span class="flex flex-col items-center gap-1"><flux:icon.meh class="size-8 text-yellow-500" /><span class="text-xs">OK</span></span></flux:slider.tick>
                        <flux:slider.tick value="4"><span class="flex flex-col items-center gap-1"><flux:icon.smile class="size-8 text-lime-500" /><span class="text-xs">Good</span></span></flux:slider.tick>
                        <flux:slider.tick value="5"><span class="flex flex-col items-center gap-1"><flux:icon.laugh class="size-8 text-green-500" /><span class="text-xs">Great</span></span></flux:slider.tick>
                    </flux:slider>
                </flux:field>

                <flux:field>
                    <flux:label>Motivation to train</flux:label>
                    <flux:slider wire:model.live.debounce.250ms="motivation" min="1" max="5" step="1">
                        <flux:slider.tick value="1"><span class="flex flex-col items-start gap-1"><flux:icon.angry class="size-8 text-red-500" /><span class="text-xs">None</span></span></flux:slider.tick>
                        <flux:slider.tick value="2"><span class="flex flex-col items-center gap-1"><flux:icon.frown class="size-8 text-orange-500" /><span class="text-xs">Low</span></span></flux:slider.tick>
                        <flux:slider.tick value="3"><span class="flex flex-col items-center gap-1"><flux:icon.meh class="size-8 text-yellow-500" /><span class="text-xs">OK</span></span></flux:slider.tick>
                        <flux:slider.tick value="4"><span class="flex flex-col items-center gap-1"><flux:icon.smile class="size-8 text-lime-500" /><span class="text-xs">High</span></span></flux:slider.tick>
                        <flux:slider.tick value="5"><span class="flex flex-col items-center gap-1"><flux:icon.laugh class="size-8 text-green-500" /><span class="text-xs">Pumped</span></span></flux:slider.tick>
                    </flux:slider>
                </flux:field>

                <flux:field>
                    <flux:label>Muscle soreness</flux:label>
                    <flux:slider wire:model.live.debounce.250ms="soreness" min="1" max="5" step="1">
                        <flux:slider.tick value="1"><span class="flex flex-col items-start gap-1"><flux:icon.angry class="size-8 text-red-500" /><span class="text-xs">Very sore</span></span></flux:slider.tick>
                        <flux:slider.tick value="2"><span class="flex flex-col items-center gap-1"><flux:icon.frown class="size-8 text-orange-500" /><span class="text-xs">Sore</span></span></flux:slider.tick>
                        <flux:slider.tick value="3"><span class="flex flex-col items-center gap-1"><flux:icon.meh class="size-8 text-yellow-500" /><span class="text-xs">Moderate</span></span></flux:slider.tick>
                        <flux:slider.tick value="4"><span class="flex flex-col items-center gap-1"><flux:icon.smile class="size-8 text-lime-500" /><span class="text-xs">Slight</span></span></flux:slider.tick>
                        <flux:slider.tick value="5"><span class="flex flex-col items-center gap-1"><flux:icon.laugh class="size-8 text-green-500" /><span class="text-xs">None</span></span></flux:slider.tick>
                    </flux:slider>
                </flux:field>

                <flux:field>
                    <flux:label>Energy</flux:label>
                    <flux:slider wire:model.live.debounce.250ms="energy" min="1" max="5" step="1">
                        <flux:slider.tick value="1"><span class="flex flex-col items-start gap-1"><flux:icon.battery class="size-8 text-red-500" /><span class="text-xs">Empty</span></span></flux:slider.tick>
                        <flux:slider.tick value="2"><span class="flex flex-col items-center gap-1"><flux:icon.battery-low class="size-8 text-orange-500" /><span class="text-xs">Low</span></span></flux:slider.tick>
                        <flux:slider.tick value="3"><span class="flex flex-col items-center gap-1"><flux:icon.battery-medium class="size-8 text-yellow-500" /><span class="text-xs">OK</span></span></flux:slider.tick>
                        <flux:slider.tick value="4"><span class="flex flex-col items-center gap-1"><flux:icon.battery-full class="size-8 text-lime-500" /><span class="text-xs">Good</span></span></flux:slider.tick>
                        <flux:slider.tick value="5"><span class="flex flex-col items-center gap-1"><flux:icon.battery-charging class="size-8 text-green-500" /><span class="text-xs">Full</span></span></flux:slider.tick>
                    </flux:slider>
                </flux:field>
            </flux:card>

            <flux:card class="space-y-6">
                <flux:heading size="lg">Vitals</flux:heading>

                <flux:field>
                    <flux:label>Resting heart rate (bpm)</flux:label>
                    <flux:input
                        type="number"
                        wire:model.live.debounce.250ms="restingHeartRate"
                        min="30"
                        max="200"
                        class="max-w-[160px]"
                    />
                </flux:field>

                <flux:text class="text-sm">
                    RHR delta: {{ $restingHeartRate - $restingHeartRateBaseline }} bpm · score {{ $this->rhrScore ?? '—' }}/5
                </flux:text>

                <flux:field>
                    <flux:label>HRV</flux:label>
                    <flux:input
                        type="number"
                        wire:model.live.debounce.250ms="hrv"
                        min="0"
                        class="max-w-[160px]"
                    />
                    <flux:description>Stored for trend analysis only — not used in readiness score.</flux:description>
                </flux:field>
            </flux:card>
        </div>

        <aside class="lg:sticky lg:top-6 lg:self-start">
            <flux:card class="space-y-6">
                <flux:heading size="lg">Readiness Score</flux:heading>

                <flux:field>
                    <flux:label>Baseline RHR (bpm)</flux:label>
                    <flux:input
                        type="number"
                        wire:model.live.debounce.250ms="restingHeartRateBaseline"
                        min="30"
                        max="200"
                        class="max-w-[120px]"
                    />
                    <flux:description>Normally averaged from last 7 days.</flux:description>
                </flux:field>

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
                    <flux:description>Scores of 1 (worst bucket) are substituted with this value.</flux:description>
                </flux:field>

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

                <div class="space-y-2 text-sm">
                    <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Sleep (weighted)</div>
                    <dl class="space-y-1">
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">Quality</dt>
                            <dd class="tabular-nums">{{ $this->adjustScore($sleepQuality) }} × 0.40 = {{ number_format($this->sleepQualityWeighted, 2) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">Duration</dt>
                            <dd class="tabular-nums">{{ $this->sleepDurationScore !== null ? $this->adjustScore($this->sleepDurationScore) : '—' }} × 0.40 = {{ $this->sleepDurationWeighted !== null ? number_format($this->sleepDurationWeighted, 2) : '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">Altitude</dt>
                            <dd class="tabular-nums">{{ $this->altitudeScore !== null ? $this->adjustScore($this->altitudeScore) : '—' }} × 0.20 = {{ $this->altitudeWeighted !== null ? number_format($this->altitudeWeighted, 2) : '—' }}</dd>
                        </div>
                    </dl>
                    <div class="flex justify-between gap-4 border-t border-zinc-200 dark:border-white/10 pt-1.5 font-medium">
                        <span>Sleep total</span>
                        <span class="tabular-nums">{{ $this->sleepScore !== null ? number_format($this->sleepScore, 2) : '—' }}</span>
                    </div>
                </div>

                <flux:separator />

                <div class="space-y-2 text-sm">
                    <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">RHR (computed)</div>
                    <dl class="space-y-1">
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">Today</dt>
                            <dd class="tabular-nums">{{ $restingHeartRate }} bpm</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">Baseline</dt>
                            <dd class="tabular-nums">{{ $restingHeartRateBaseline }} bpm</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">Δ</dt>
                            <dd class="tabular-nums">{{ $this->rhrDelta }}</dd>
                        </div>
                    </dl>
                    <div class="flex justify-between gap-4 border-t border-zinc-200 dark:border-white/10 pt-1.5 font-medium">
                        <span>RHR score</span>
                        <span class="tabular-nums">{{ $this->rhrScore !== null ? $this->adjustScore($this->rhrScore) : '—' }}</span>
                    </div>
                </div>

                <flux:separator />

                <div class="space-y-2 text-sm">
                    <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Components</div>
                    <dl class="space-y-1">
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">Sleep</dt>
                            <dd class="tabular-nums">{{ $this->sleepScore !== null ? number_format($this->sleepScore, 2) : '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">Condition</dt>
                            <dd class="tabular-nums">{{ $this->adjustScore($condition) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">Mood</dt>
                            <dd class="tabular-nums">{{ $this->adjustScore($mood) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">Motivation</dt>
                            <dd class="tabular-nums">{{ $this->adjustScore($motivation) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">Soreness</dt>
                            <dd class="tabular-nums">{{ $this->adjustScore($soreness) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">Energy</dt>
                            <dd class="tabular-nums">{{ $this->adjustScore($energy) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">RHR</dt>
                            <dd class="tabular-nums">{{ $this->rhrScore !== null ? $this->adjustScore($this->rhrScore) : '—' }}</dd>
                        </div>
                    </dl>
                    <div class="flex justify-between gap-4 border-t border-zinc-200 dark:border-white/10 pt-1.5 font-medium">
                        <span>Sum</span>
                        <span class="tabular-nums">{{ $this->readinessComponentsSum !== null ? number_format($this->readinessComponentsSum, 2) : '—' }}</span>
                    </div>
                    <div class="flex justify-between gap-4 font-medium">
                        <span>÷ 7</span>
                        <span class="tabular-nums">{{ $this->readinessScore !== null ? number_format($this->readinessScore, 2) : '—' }}</span>
                    </div>
                </div>
            </flux:card>
        </aside>
    </div>
</flux:main>
