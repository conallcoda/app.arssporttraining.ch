@props([
    'field',
    'errorName' => null,
    'showHeader' => true,
    'showError' => true,
    'helpPosition' => 'top',
    'helpAlign' => 'end',
])

<flux:field {{ $attributes }}>
    @if ($showHeader)
        <x-form-kit::form.field-header :field="$field" :help-position="$helpPosition" :help-align="$helpAlign">
            @isset($actions)
                <x-slot:actions>{{ $actions }}</x-slot:actions>
            @endisset
        </x-form-kit::form.field-header>
    @endif

    {{ $slot }}

    @if ($showError && filled($errorName))
        <flux:error name="{{ $errorName }}" />
    @endif
</flux:field>
