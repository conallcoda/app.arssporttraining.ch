<div class="space-y-6">
    <div>
        <flux:heading size="lg">Readiness Check</flux:heading>
        <flux:text class="mt-2">How are you feeling today?</flux:text>
    </div>

    <flux:slider wire:model.live="score" min="1" max="4" step="1">
        <flux:slider.tick value="1">Rest</flux:slider.tick>
        <flux:slider.tick value="2">Recovery</flux:slider.tick>
        <flux:slider.tick value="3">Train Smart</flux:slider.tick>
        <flux:slider.tick value="4">Ready</flux:slider.tick>
    </flux:slider>

    <flux:button
        wire:click="submitReadiness"
        variant="primary"
        class="w-full"
    >
        Submit
    </flux:button>
</div>
