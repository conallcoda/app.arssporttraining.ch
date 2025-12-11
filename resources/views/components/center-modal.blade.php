@props(['name', 'title' => null, 'subtitle' => null, 'maxWidth' => 'max-w-4xl'])

<flux:modal :name="$name" {{ $attributes->merge(['class' => $maxWidth]) }}>
    <div class="space-y-6">
        @if ($title)
            <div>
                <flux:heading size="lg">{{ $title }}</flux:heading>
                @if ($subtitle)
                    <flux:subheading>{{ $subtitle }}</flux:subheading>
                @endif
            </div>
        @endif

        {{ $slot }}
    </div>
</flux:modal>
