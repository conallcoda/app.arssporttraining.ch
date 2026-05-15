@php
    $showAdminDetails = $showAdminDetails ?? false;
    $showScoreHeader = $showScoreHeader ?? true;
@endphp

<div class="space-y-6">
    @if ($showScoreHeader)
        <flux:heading size="lg">Readiness Score</flux:heading>

        <div class="flex items-baseline gap-2">
            <span class="text-5xl font-semibold tabular-nums">
                {{ $viewData['readinessScore'] !== null ? number_format($viewData['readinessScore'], 2) : '—' }}
            </span>
            <span class="text-zinc-500">/ 5</span>
        </div>

        @if ($viewData['trafficLightMeta'])
            <div class="inline-flex rounded-full border px-3 py-1 text-sm font-medium {{ $viewData['trafficLightMeta']['classes'] }}">
                {{ $viewData['trafficLightMeta']['label'] }}
            </div>
        @endif

        @unless ($showAdminDetails)
            <flux:text class="text-sm text-zinc-500">
                Complete the survey to calculate today’s readiness score.
            </flux:text>
        @endunless
    @endif

    @if ($showAdminDetails)
        <flux:separator />

        <div class="space-y-2 text-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Sleep (weighted)</div>
            <dl class="space-y-1">
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Quality</dt>
                    <dd class="tabular-nums">{{ $viewData['sleepQualityAdjusted'] }} × 0.40 = {{ number_format($viewData['sleepQualityWeighted'], 2) }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Duration</dt>
                    <dd class="tabular-nums">{{ $viewData['sleepDurationAdjusted'] ?? '—' }} × 0.40 = {{ $viewData['sleepDurationWeighted'] !== null ? number_format($viewData['sleepDurationWeighted'], 2) : '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Altitude</dt>
                    <dd class="tabular-nums">{{ $viewData['altitudeAdjusted'] ?? '—' }} × 0.20 = {{ $viewData['altitudeWeighted'] !== null ? number_format($viewData['altitudeWeighted'], 2) : '—' }}</dd>
                </div>
            </dl>
            <div class="flex justify-between gap-4 border-t border-zinc-200 dark:border-white/10 pt-1.5 font-medium">
                <span>Sleep total</span>
                <span class="tabular-nums">{{ $viewData['sleepScore'] !== null ? number_format($viewData['sleepScore'], 2) : '—' }}</span>
            </div>
        </div>

        <flux:separator />

        <div class="space-y-2 text-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">RHR (computed)</div>
            <dl class="space-y-1">
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Today</dt>
                    <dd class="tabular-nums">{{ $viewData['metric']->restingHeartRate ?? '—' }} bpm</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Baseline</dt>
                    <dd class="tabular-nums">{{ $viewData['metric']->restingHeartRateBaseline ?? '—' }} bpm</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Δ</dt>
                    <dd class="tabular-nums">{{ $viewData['rhrDelta'] ?? '—' }}</dd>
                </div>
            </dl>
            <div class="flex justify-between gap-4 border-t border-zinc-200 dark:border-white/10 pt-1.5 font-medium">
                <span>RHR score</span>
                <span class="tabular-nums">{{ $viewData['rhrAdjusted'] ?? '—' }}</span>
            </div>
        </div>

        <flux:separator />

        <div class="space-y-2 text-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Self-report</div>
            <dl class="space-y-1">
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Condition</dt>
                    <dd class="tabular-nums">{{ $viewData['conditionAdjusted'] ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Mood</dt>
                    <dd class="tabular-nums">{{ $viewData['moodAdjusted'] ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Motivation</dt>
                    <dd class="tabular-nums">{{ $viewData['motivationAdjusted'] ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Soreness</dt>
                    <dd class="tabular-nums">{{ $viewData['sorenessAdjusted'] ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Energy</dt>
                    <dd class="tabular-nums">{{ $viewData['energyAdjusted'] ?? '—' }}</dd>
                </div>
            </dl>
            <div class="flex justify-between gap-4 border-t border-zinc-200 dark:border-white/10 pt-1.5 font-medium">
                <span>Component sum</span>
                <span class="tabular-nums">{{ $viewData['readinessComponentsSum'] !== null ? number_format($viewData['readinessComponentsSum'], 2) : '—' }}</span>
            </div>
        </div>

        <flux:separator />

        <div class="space-y-2 text-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Final score</div>
            <dl class="space-y-1">
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Component sum</dt>
                    <dd class="tabular-nums">{{ $viewData['readinessComponentsSum'] !== null ? number_format($viewData['readinessComponentsSum'], 2) : '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Divided by</dt>
                    <dd class="tabular-nums">{{ $viewData['readinessScoreDivisor'] }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Raw score</dt>
                    <dd class="tabular-nums">{{ $viewData['readinessScoreRaw'] !== null ? number_format($viewData['readinessScoreRaw'], 4) : '—' }}</dd>
                </div>
            </dl>
            <div class="flex justify-between gap-4 border-t border-zinc-200 dark:border-white/10 pt-1.5 font-medium">
                <span>Rounded score</span>
                <span class="tabular-nums">{{ $viewData['readinessScoreRounded'] !== null ? number_format($viewData['readinessScoreRounded'], 1) : '—' }}</span>
            </div>
        </div>
    @endif
</div>
