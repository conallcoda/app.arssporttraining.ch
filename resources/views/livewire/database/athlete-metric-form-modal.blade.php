<div>
    <x-cms::modal
        :name="$name"
        :flyout="$flyout"
        :max-width="$maxWidth"
        x-on:close="Livewire.dispatch('{{ $name }}.closed')"
        x-on:focus-field.window="
            const content = $el.querySelector('[data-modal-focus-content]');
            content.style.visibility = 'hidden';
            setTimeout(() => {
                focusModalField($el, $event.detail.field, $event.detail.index);
                content.style.visibility = 'visible';
            }, 150)
        "
        x-on:keydown.enter="handleModalEnterSubmit($event, $wire)">
        <x-cms::modal.header :title="$activeTitle ?? $title" />
        <x-cms::modal.body class="space-y-6">
            @if ($openCount > 0)
                <form id="modal-form-{{ $name }}" wire:submit="submit" class="space-y-4">
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
                            @include('livewire.database.partials.heart-rate-preview-sections', [
                                'sections' => $this->heartRatePreviewSections,
                                'recordedAt' => $data['recorded_at'] ?? null,
                            ])
                        @endif
                    @endif
                </form>
            @endif
        </x-cms::modal.body>
        <x-cms::modal.footer>
            <div class="flex items-center gap-2">
                <flux:button type="submit" form="modal-form-{{ $name }}" variant="primary" class="flex-1">{{ $submitLabel }}</flux:button>
                <flux:modal.close>
                    <flux:button variant="ghost">{{ $cancelLabel }}</flux:button>
                </flux:modal.close>
                @if ($showDelete && !empty($data['id']))
                    <flux:spacer />
                    <flux:button variant="ghost" icon="trash-2" wire:click="requestDelete" />
                @endif
            </div>
        </x-cms::modal.footer>
    </x-cms::modal>
</div>
