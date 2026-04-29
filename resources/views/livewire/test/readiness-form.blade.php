<flux:main>
    <style>
        [data-flux-slider-tick]:first-child {
            translate: -10px 0 !important;
            align-items: flex-start !important;
            min-width: 0 !important;
        }
    </style>

    <div class="grid gap-8 lg:grid-cols-[1fr_340px]">
        @include('livewire.readiness.partials.survey-fields', [
            'bindingPrefix' => 'form.',
            'state' => $form,
            'viewData' => $this->readinessViewData,
        ])

        <aside class="space-y-6 lg:sticky lg:top-6 lg:self-start">
            <flux:card class="space-y-6">
                <flux:heading size="lg">Test Config</flux:heading>

                <flux:field>
                    <flux:label>Baseline RHR (bpm)</flux:label>
                    <flux:input
                        type="number"
                        wire:model.live.debounce.250ms="form.restingHeartRateBaseline"
                        min="30"
                        max="200"
                        class="max-w-[120px]"
                    />
                    <flux:description>Normally averaged from the last 7 readiness submissions.</flux:description>
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
                    <flux:description>Scores of 1 are substituted with this value.</flux:description>
                </flux:field>
            </flux:card>

            <flux:card class="space-y-6">
                @include('livewire.readiness.partials.breakdown-card', [
                    'viewData' => $this->readinessViewData,
                    'showAdminDetails' => true,
                ])
            </flux:card>
        </aside>
    </div>
</flux:main>
