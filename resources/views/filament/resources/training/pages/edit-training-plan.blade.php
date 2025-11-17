<x-filament-panels::page>
    <div>
        <flux:card>
            <livewire:training.training-season :season="$this->record" />
        </flux:card>

        <flux:modal name="example-modal" class="md:w-96" variant="flyout">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Example Modal</flux:heading>
                    <flux:subheading class="mt-2">
                        This is a Flux UI modal for {{ $this->record->name }}
                    </flux:subheading>
                </div>

                <flux:input label="Example Input" placeholder="Type something..." />

                <div class="flex">
                    <flux:spacer />
                    <flux:button variant="primary">Save Changes</flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</x-filament-panels::page>
