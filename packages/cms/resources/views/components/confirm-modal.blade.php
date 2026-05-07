@props([
    'name',
    'heading',
    'description' => '',
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'variant' => 'danger',
    'action' => null,
])

<flux:modal :name="$name" class="min-w-[22rem]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $heading }}</flux:heading>
            @if ($description)
                <flux:text class="mt-2">
                    {!! nl2br(e($description)) !!}
                </flux:text>
            @endif
        </div>
        <div class="flex gap-2">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost">{{ $cancelLabel }}</flux:button>
            </flux:modal.close>
            <flux:button variant="{{ $variant }}" wire:click="{{ $action }}">
                {{ $confirmLabel }}
            </flux:button>
        </div>
    </div>
</flux:modal>
