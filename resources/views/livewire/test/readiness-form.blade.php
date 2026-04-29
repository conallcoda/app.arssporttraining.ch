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

        <aside class="lg:sticky lg:top-6 lg:self-start">
            @include('livewire.readiness.partials.score-card', [
                'bindingPrefix' => 'form.',
                'viewData' => $this->readinessViewData,
                'showAdminDetails' => true,
                'showBaselineField' => true,
                'showExtremeOffsetField' => true,
            ])
        </aside>
    </div>
</flux:main>
