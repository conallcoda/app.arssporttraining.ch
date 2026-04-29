<div class="space-y-6">
    <div>
        <flux:heading size="lg">Readiness Check</flux:heading>
        <flux:text class="mt-2">Please complete the readiess survey before beginning your training for the day.
        </flux:text>
    </div>

    @include('livewire.readiness.partials.survey-fields', [
        'bindingPrefix' => 'form.',
        'state' => $form,
        'viewData' => $this->readinessViewData,
        'showRhrSummary' => false,
    ])

    <flux:button wire:click="submitReadiness" variant="primary" class="w-full">
        Submit
    </flux:button>
</div>
