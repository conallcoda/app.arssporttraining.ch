@php
    $detailsFieldset = $this->detailsFieldsetsByName['details'] ?? null;
    $adminFieldset = $this->detailsFieldsetsByName['admin'] ?? null;
    $coachField = collect($adminFieldset?->fields ?? [])->firstWhere('name', 'owner_id');
    $statusLabel = $this->data['setupStatusLabel'] ?? 'Unknown';
    $statusColor = $this->data['setupStatusColor'] ?? 'zinc';

    $readinessSnapshot = $this->readinessSnapshot;
    $readinessViewData = $this->readinessViewData;
    $heartRateSnapshot = $this->heartRateSnapshot;
    $oneRepMaxSnapshot = $this->oneRepMaxSnapshot;
    $oneRepMaxPreviewGrid = $this->oneRepMaxPreviewGrid;
    $oneRepMaxMetric = $oneRepMaxSnapshot['instance'] ?? null;
@endphp

<div class="space-y-6">
    <flux:tab.group>
        <div class="sticky top-0 z-10 bg-white dark:bg-zinc-800">
            <div class="space-y-6 py-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <flux:text size="sm" variant="subtle">{{ $this->entityName }}</flux:text>
                        <flux:heading size="xl" level="1">{{ $this->pageTitle }}</flux:heading>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:button type="button" variant="ghost" icon="arrow-left" x-on:click="window.history.back()">
                            Back
                        </flux:button>
                        <flux:button type="submit" variant="primary" form="athlete-details-form">Save</flux:button>
                    </div>
                </div>

                <flux:tabs wire:model.live="activeTab">
                    <flux:tab name="general">General</flux:tab>
                    <flux:tab name="readiness">Readiness</flux:tab>
                    <flux:tab name="heart_rate">Heart Rate</flux:tab>
                    <flux:tab name="one_rep_max">One Rep Max</flux:tab>
                </flux:tabs>
            </div>
        </div>

        <flux:tab.panel name="general" class="pt-6">
            <form id="athlete-details-form" wire:submit="save">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-12">
                    <div class="md:col-span-7">
                        <x-cms::section title="Details">
                            @if ($detailsFieldset)
                                <x-form-kit::form.fieldset
                                    :fieldset="$detailsFieldset"
                                    :prefix="$detailsFieldset->prefix ?? 'data'"
                                    :showLegend="false"
                                />
                            @endif
                        </x-cms::section>
                    </div>

                    <div class="md:col-span-5">
                        <x-cms::section title="Admin">
                            @if ($coachField)
                                <x-form-kit::form.field
                                    wire:key="field-data-{{ $coachField->name }}"
                                    :field="$coachField"
                                    prefix="data"
                                />
                            @endif

                            <div class="space-y-2">
                                <flux:text size="sm" class="font-medium text-zinc-700 dark:text-zinc-300">Setup Status</flux:text>
                                <div>
                                    <flux:badge :color="$statusColor">{{ $statusLabel }}</flux:badge>
                                </div>
                            </div>

                            <div class="space-y-3 pt-2">
                                <flux:button type="button" variant="primary" class="w-full justify-center" wire:click="sendSetupAccountEmail">
                                    Send Setup Email
                                </flux:button>

                                <flux:button
                                    type="button"
                                    variant="ghost"
                                    class="w-full justify-center"
                                    x-on:click="Livewire.dispatch('open-{{ $this->changePasswordModalName() }}', {
                                        title: 'Change Password',
                                        data: {{ Js::from(['id' => $this->record, '_name' => $this->pageTitle]) }}
                                    })"
                                >
                                    Change Password
                                </flux:button>
                            </div>
                        </x-cms::section>
                    </div>
                </div>
            </form>
        </flux:tab.panel>

        <flux:tab.panel name="readiness" class="pt-6">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <x-cms::section title="Readiness History">
                        <livewire:database.athlete-metric-list
                            :athlete-id="$this->record"
                            forced-metric="readiness"
                            :show-tabs="false"
                            :prefix-url="true"
                            :options="['showAddButton' => true]"
                            wire:key="athlete-readiness-list-{{ $this->record }}"
                        />
                    </x-cms::section>
                </div>

                <div>
                    <x-cms::section title="Preview">
                        @if ($readinessViewData)
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-lg border border-zinc-700 bg-zinc-800/60 px-3 py-2">
                                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-400">Recorded</div>
                                    <div class="mt-1 text-lg font-semibold tabular-nums text-white">{{ $readinessSnapshot['recordedAt'] ?? '—' }}</div>
                                </div>
                                <div class="rounded-lg border border-zinc-700 bg-zinc-800/60 px-3 py-2">
                                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-400">Summary</div>
                                    <div class="mt-1 flex items-baseline gap-2">
                                        <span class="text-5xl font-semibold tabular-nums text-white">
                                            {{ $readinessViewData['readinessScore'] !== null ? number_format($readinessViewData['readinessScore'], 2) : '—' }}
                                        </span>
                                        <span class="text-zinc-400">/ 5</span>
                                    </div>

                                    @if ($readinessViewData['trafficLightMeta'])
                                        <div class="mt-3 inline-flex rounded-full border px-3 py-1 text-sm font-medium {{ $readinessViewData['trafficLightMeta']['classes'] }}">
                                            {{ $readinessViewData['trafficLightMeta']['label'] }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <details class="group rounded-lg border border-zinc-700 bg-zinc-800/30">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-zinc-200">
                                    <span>Score Breakdown</span>
                                    <flux:icon.chevron-down class="size-4 transition-transform group-open:rotate-180" />
                                </summary>

                                <div class="border-t border-zinc-700 px-4 py-4">
                                    @include('livewire.readiness.partials.breakdown-card', [
                                        'viewData' => $readinessViewData,
                                        'showAdminDetails' => true,
                                        'showScoreHeader' => false,
                                    ])
                                </div>
                            </details>
                        @else
                            <div class="rounded-lg border border-dashed border-zinc-700 bg-zinc-800/30 p-6 text-center">
                                <flux:text class="text-zinc-400">No complete readiness score is available yet.</flux:text>
                            </div>
                        @endif
                    </x-cms::section>
                </div>
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="heart_rate" class="pt-6">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <x-cms::section title="Heart Rate History">
                        <livewire:database.athlete-metric-list
                            :athlete-id="$this->record"
                            forced-metric="heartRate"
                            :show-tabs="false"
                            :prefix-url="true"
                            :options="['showAddButton' => true]"
                            wire:key="athlete-heart-rate-list-{{ $this->record }}"
                        />
                    </x-cms::section>
                </div>

                <div class="space-y-4">
                    @if ($heartRateSnapshot['isAvailable'] ?? false)
                        @include('livewire.database.partials.heart-rate-preview-sections', [
                            'sections' => $this->heartRatePreviewSections,
                            'recordedAt' => $heartRateSnapshot['recordedAt'] ?? null,
                        ])
                    @else
                        <div class="rounded-lg border border-dashed border-zinc-700 bg-zinc-800/30 p-6 text-center">
                            <flux:text class="text-zinc-400">Record a heart rate metric to unlock bike and jogging previews.</flux:text>
                        </div>
                    @endif
                </div>
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="one_rep_max" class="pt-6">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <x-cms::section title="1RM History">
                        <livewire:database.athlete-metric-list
                            :athlete-id="$this->record"
                            forced-metric="oneRepMax"
                            :show-tabs="false"
                            :prefix-url="true"
                            :options="['showAddButton' => true]"
                            wire:key="athlete-one-rep-max-list-{{ $this->record }}"
                        />
                    </x-cms::section>
                </div>

                <div>
                    <x-cms::section title="Preview">
                        @if ($oneRepMaxPreviewGrid && $oneRepMaxMetric instanceof \App\Data\Athlete\Metric\Metrics\OneRepMaxMetric)
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div class="rounded-lg border border-zinc-700 bg-zinc-800/60 px-3 py-2">
                                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-400">Recorded</div>
                                    <div class="mt-1 text-lg font-semibold tabular-nums text-white">{{ $oneRepMaxSnapshot['recordedAt'] ?? '—' }}</div>
                                </div>
                                <div class="rounded-lg border border-zinc-700 bg-zinc-800/60 px-3 py-2">
                                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-400">Current 1RM</div>
                                    <div class="mt-1 text-lg font-semibold tabular-nums text-white">{{ $oneRepMaxMetric->estimatedLabel() }}kg</div>
                                </div>
                                <div class="rounded-lg border border-zinc-700 bg-zinc-800/60 px-3 py-2">
                                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-400">Measured Set</div>
                                    <div class="mt-1 text-lg font-semibold tabular-nums text-white">
                                        {{ $oneRepMaxMetric->measuredReps ?? '—' }} x {{ $oneRepMaxMetric->measuredWeightLabel() }}kg
                                    </div>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <x-training.plan-grid
                                    :grid="$oneRepMaxPreviewGrid"
                                    name="Strength - 1RM 100% Template"
                                    :show-header="false"
                                    :show-menu="false"
                                    :editable="false"
                                    :collapse-weeks="false"
                                />
                            </div>
                        @else
                            <div class="rounded-lg border border-dashed border-zinc-700 bg-zinc-800/30 p-6 text-center">
                                <flux:text class="text-zinc-400">Record a 1RM metric to unlock the five session strength preview.</flux:text>
                            </div>
                        @endif
                    </x-cms::section>
                </div>
            </div>
        </flux:tab.panel>
    </flux:tab.group>

    <livewire:cms.form-modal
        :name="$this->changePasswordModalName()"
        title="Change Password"
        :form-data-class="$this->changePasswordFormClass()"
        submit-label="Save"
        wire:key="athlete-change-password-modal-{{ $this->record }}"
    />
</div>
