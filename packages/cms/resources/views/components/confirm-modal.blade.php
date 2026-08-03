@props([
    'name',
    'heading',
    'description' => '',
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'variant' => 'danger',
    'action' => null,
])

<x-cms::modal :name="$name" max-width="min-w-[22rem]">
    <x-cms::modal.header :title="$heading" :description="$description" />
    <x-cms::modal.footer>
        <div class="flex gap-2">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost">{{ $cancelLabel }}</flux:button>
            </flux:modal.close>
            <flux:button variant="{{ $variant }}" wire:click="{{ $action }}">
                {{ $confirmLabel }}
            </flux:button>
        </div>
    </x-cms::modal.footer>
</x-cms::modal>
