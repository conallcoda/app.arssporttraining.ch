<div>
    <flux:modal name="readiness-check" class="w-[96vw] max-w-none sm:max-w-3xl">
        <livewire:athlete.readiness-check :date="$readinessDate" :key="'readiness-check-' . $readinessDate . '-' . $readinessModalOpened" />
    </flux:modal>
</div>
