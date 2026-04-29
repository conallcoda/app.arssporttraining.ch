<div>
    <flux:modal
        :name="$name"
        :flyout="$flyout"
        :class="$maxWidth"
        x-on:close="Livewire.dispatch('{{ $name }}.closed')"
        x-on:focus-field.window="
            const content = $el.querySelector('.space-y-6');
            content.style.visibility = 'hidden';
            setTimeout(() => {
                focusModalField($el, $event.detail.field, $event.detail.index);
                content.style.visibility = 'visible';
            }, 150)
        "
        x-on:keydown.enter="handleModalEnterSubmit($event, $wire)">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $activeTitle ?? $title }}</flux:heading>
            @if ($openCount > 0)
                <form wire:submit="submit" class="space-y-4">
                    @if ($groupMode && !empty($availableAthletes))
                        <flux:field>
                            <flux:label>{{ __('Athlete') }}</flux:label>
                            <flux:select wire:model.live="data.user_id" variant="listbox" placeholder="{{ __('Select athlete...') }}">
                                @foreach ($availableAthletes as $athlete)
                                    <flux:select.option :value="$athlete['id']">{{ $athlete['name'] }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            @error('data.user_id')
                                <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>
                            @enderror
                        </flux:field>
                    @endif

                    @if ($this->isReadinessMetric)
                        <div class="space-y-4">
                            @if ($this->showReadinessBreakdownTab)
                                <flux:tab.group>
                                    <flux:tabs wire:model.live="readinessModalTab">
                                        <flux:tab name="data">{{ __('Data') }}</flux:tab>
                                        <flux:tab name="breakdown">{{ __('Breakdown') }}</flux:tab>
                                    </flux:tabs>

                                    <flux:tab.panel name="data" class="!px-0">
                                        <div class="space-y-4">
                                            <flux:field>
                                                <flux:label>{{ __('Date') }}</flux:label>
                                                <flux:input type="date" wire:model.live="data.recorded_at" />
                                            </flux:field>

                                            @include('livewire.readiness.partials.survey-fields', [
                                                'bindingPrefix' => 'data.data.',
                                                'state' => $data['data'] ?? [],
                                                'viewData' => $this->readinessViewData,
                                            ])
                                        </div>
                                    </flux:tab.panel>

                                    <flux:tab.panel name="breakdown" class="!px-0">
                                        @include('livewire.readiness.partials.breakdown-card', [
                                            'viewData' => $this->readinessViewData,
                                            'showAdminDetails' => true,
                                        ])
                                    </flux:tab.panel>
                                </flux:tab.group>
                            @else
                                <flux:field>
                                    <flux:label>{{ __('Date') }}</flux:label>
                                    <flux:input type="date" wire:model.live="data.recorded_at" />
                                </flux:field>

                                @include('livewire.readiness.partials.survey-fields', [
                                    'bindingPrefix' => 'data.data.',
                                    'state' => $data['data'] ?? [],
                                    'viewData' => $this->readinessViewData,
                                ])
                            @endif
                        </div>
                    @else
                        @foreach ($this->fieldsets as $item)
                            @if ($item instanceof \Coda\FormKit\FormFieldsetGroup)
                                <x-form-kit::form.fieldset-tabs :group="$item" />
                            @else
                                <x-form-kit::form.fieldset
                                    :fieldset="$item"
                                    :prefix="$item->prefix ?? 'data'"
                                    :showLegend="count($this->fieldsets) > 1"
                                />
                            @endif
                        @endforeach

                        @if ($this->isHeartRateMetric)
                            <div class="grid gap-4 xl:grid-cols-2">
                                @foreach ($this->heartRatePreviewSections as $section)
                                    <fieldset class="min-w-0 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 space-y-4 [&>legend+*]:!mt-0">
                                        <legend class="mb-0 px-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                            {{ $section['title'] }} Preview
                                        </legend>

                                        <flux:text class="text-sm text-zinc-500">Updates from max heart rate and anaerobic threshold.</flux:text>

                                        <div class="grid grid-cols-2 gap-3">
                                            <div class="rounded-lg border border-zinc-700 bg-zinc-800/60 px-3 py-2">
                                                <div class="text-xs font-medium uppercase tracking-wide text-zinc-400">Max HR</div>
                                                <div class="mt-1 text-lg font-semibold tabular-nums text-white">{{ $section['maxHeartRate'] ?? '—' }}</div>
                                            </div>
                                            <div class="rounded-lg border border-zinc-700 bg-zinc-800/60 px-3 py-2">
                                                <div class="text-xs font-medium uppercase tracking-wide text-zinc-400">IAT</div>
                                                <div class="mt-1 text-lg font-semibold tabular-nums text-white">{{ $section['anaerobicThreshold'] !== null ? $section['anaerobicThreshold'].'%' : '—' }}</div>
                                            </div>
                                        </div>

                                        <div class="overflow-hidden rounded-xl border border-zinc-700">
                                            <table class="min-w-full text-sm">
                                                <thead class="bg-zinc-800/90 text-zinc-300">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left font-medium">Zone</th>
                                                        <th class="px-3 py-2 text-left font-medium">BPM</th>
                                                        <th class="px-3 py-2 text-left font-medium">% of Max HR</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($section['rows'] as $row)
                                                        <tr class="{{ $row['classes'] }}">
                                                            <td class="px-3 py-2 font-semibold">{{ $row['name'] }}</td>
                                                            <td class="px-3 py-2 tabular-nums">{{ $row['bpm'] }}</td>
                                                            <td class="px-3 py-2 tabular-nums">{{ $row['percent'] }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </fieldset>
                                @endforeach
                            </div>
                        @endif
                    @endif
                    <div class="flex items-center gap-2 pt-4">
                        <flux:button type="submit" variant="primary" class="flex-1">{{ $submitLabel }}</flux:button>
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ $cancelLabel }}</flux:button>
                        </flux:modal.close>
                        @if ($showDelete && !empty($data['id']))
                            <flux:spacer />
                            <flux:button variant="ghost" icon="trash-2" wire:click="requestDelete" />
                        @endif
                    </div>
                </form>
            @endif
        </div>
    </flux:modal>
</div>
