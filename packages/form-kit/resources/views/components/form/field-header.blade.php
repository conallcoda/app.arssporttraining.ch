@props(['field', 'helpPosition' => 'top', 'helpAlign' => 'end'])

@php
    $actionsContent = isset($actions) ? trim((string) $actions) : '';
@endphp

<div {{ $attributes->class(['mb-2 flex items-center justify-between gap-3']) }}>
    <div class="min-w-0 flex-1">
        <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
    </div>

    @if (filled($field->helpText) || $actionsContent !== '')
        <div class="flex shrink-0 items-center gap-2">
            @if (filled($field->helpText))
                <x-form-kit::form.help-tooltip :content="$field->helpText" :position="$helpPosition" :align="$helpAlign" />
            @endif

            @if ($actionsContent !== '')
                {{ $actions }}
            @endif
        </div>
    @endif
</div>
