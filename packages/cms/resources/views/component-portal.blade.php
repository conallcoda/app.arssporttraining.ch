<div>
    @foreach ($stack as $item)
        <flux:modal
            :name="$item['id']"
            :variant="$item['variant']"
            x-init="$nextTick(() => $flux.modal('{{ $item['id'] }}').show())"
            x-on:close="$wire.removeFromStack('{{ $item['id'] }}')"
        >
            <div class="space-y-6">
                @if ($item['title'])
                    <flux:heading size="lg">{{ $item['title'] }}</flux:heading>
                @endif

                @livewire($item['component'], $item['props'], key($item['id']))
            </div>
        </flux:modal>
    @endforeach
</div>
