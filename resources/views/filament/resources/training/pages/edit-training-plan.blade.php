<x-filament-panels::page>
    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-3">
            <flux:card>
                <livewire:training.training-tree :nodes="$this->getNodes()" />
            </flux:card>
        </div>
        <div class="col-span-9">
            <flux:card>
                <livewire:training.training-season :season="$this->record" />
            </flux:card>
        </div>
    </div>
</x-filament-panels::page>
