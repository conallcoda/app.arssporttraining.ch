@props([
    'wireModel' => null,
    'label' => null,
    'placeholder' => 'Select a color...',
    'size' => null,
])

@php
use App\Filament\Forms\Components\ColorPicker;

$colors = [
    'slate' => 'Slate',
    'gray' => 'Gray',
    'zinc' => 'Zinc',
    'neutral' => 'Neutral',
    'stone' => 'Stone',
    'red' => 'Red',
    'orange' => 'Orange',
    'amber' => 'Amber',
    'yellow' => 'Yellow',
    'lime' => 'Lime',
    'green' => 'Green',
    'emerald' => 'Emerald',
    'teal' => 'Teal',
    'cyan' => 'Cyan',
    'sky' => 'Sky',
    'blue' => 'Blue',
    'indigo' => 'Indigo',
    'violet' => 'Violet',
    'purple' => 'Purple',
    'fuchsia' => 'Fuchsia',
    'pink' => 'Pink',
    'rose' => 'Rose',
];

$colorOptions = [];
foreach ($colors as $value => $name) {
    $colorOptions[] = [
        'value' => $value,
        'name' => $name,
        'hex' => ColorPicker::getColorValue($value, 500),
    ];
}
@endphp

<flux:field>
    <flux:label>{{ $label ?? 'Color' }}</flux:label>
    <flux:select
        wire:model="{{ $wireModel }}"
        variant="listbox"
        placeholder="{{ $placeholder }}"
    >
        <?php foreach ($colorOptions as $option): ?>
            <flux:select.option value="{{ $option['value'] }}">
                <div class="flex items-center gap-2">
                    <span
                        class="w-4 h-4 rounded border border-black/10 shrink-0"
                        style="background-color: {{ $option['hex'] }};"
                    ></span>
                    <span>{{ $option['name'] }}</span>
                </div>
            </flux:select.option>
        <?php endforeach; ?>
    </flux:select>
</flux:field>
