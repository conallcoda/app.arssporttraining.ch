@props(['panelField'])

@php
    $panelFieldKey = (string) ($panelField['key'] ?? '');
    $panelFieldType = (string) ($panelField['type'] ?? 'text');
    $panelFieldPlaceholder = (string) ($panelField['placeholder'] ?? '');
    $panelFieldMeta = is_array($panelField['fieldMeta'] ?? null) ? $panelField['fieldMeta'] : [];
    $panelFieldLabel = (string) ($panelField['label'] ?? $panelFieldKey);
    $panelFieldRequired = (bool) ($panelField['required'] ?? false);
@endphp

<flux:field class="space-y-2">
    <div class="flex items-center gap-1">
        <flux:label :badge="$panelFieldRequired ? 'Required' : null">{{ $panelFieldLabel }}</flux:label>
    </div>

    @if ($panelFieldType === 'select-entity' || $panelFieldType === 'select')
        @php
            $panelOptionField = (object) $panelFieldMeta;
        @endphp
        <flux:select
            variant="{{ $panelField['variant'] ?? 'listbox' }}"
            :searchable="(bool) ($panelField['searchable'] ?? false)"
            :clearable="(bool) ($panelField['clearable'] ?? false)"
            placeholder="{{ $panelFieldPlaceholder !== '' ? $panelFieldPlaceholder : null }}"
            x-bind:invalid="modalStateFieldInvalid({{ \Illuminate\Support\Js::from($panelField) }})"
            x-on:change="updateModalStateField({{ \Illuminate\Support\Js::from($panelField) }}, $event.target.value)"
        >
            @if ($panelFieldPlaceholder !== '' && ! ($panelField['clearable'] ?? false))
                <flux:select.option value="">{{ $panelFieldPlaceholder }}</flux:select.option>
            @endif
            @foreach (($panelField['options'] ?? []) as $option)
                <flux:select.option
                    value="{{ $option['value'] ?? '' }}"
                    x-bind:selected="String({{ \Illuminate\Support\Js::from((string) ($option['value'] ?? '')) }}) === String(modalStateFieldValue({{ \Illuminate\Support\Js::from($panelField) }}))"
                >
                    @if (! empty($panelField['optionView']))
                        @include($panelField['optionView'], [
                            'value' => $option['value'] ?? '',
                            'label' => $option['label'] ?? ($option['value'] ?? ''),
                            'field' => $panelOptionField,
                        ])
                    @else
                        {{ $option['label'] ?? ($option['value'] ?? '') }}
                    @endif
                </flux:select.option>
            @endforeach
        </flux:select>
    @else
        <flux:input
            type="text"
            x-bind:value="modalStateFieldValue({{ \Illuminate\Support\Js::from($panelField) }})"
            x-bind:invalid="modalStateFieldInvalid({{ \Illuminate\Support\Js::from($panelField) }})"
            x-on:input="updateModalStateField({{ \Illuminate\Support\Js::from($panelField) }}, $event.target.value)"
            placeholder="{{ $panelFieldPlaceholder }}"
        />
    @endif

    <template x-if="modalStateFieldInvalid({{ \Illuminate\Support\Js::from($panelField) }})">
        <div class="flex items-center gap-2 text-sm text-red-500">
            <flux:icon.exclamation-triangle class="size-4 shrink-0" />
            <span x-text="modalStateFieldError({{ \Illuminate\Support\Js::from($panelField) }})"></span>
        </div>
    </template>
</flux:field>
