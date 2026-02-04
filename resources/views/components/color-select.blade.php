@props([
    'wireModel' => null,
    'label' => null,
    'placeholder' => 'Select a color...',
    'size' => null,
])

@php
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

$colorHexValues = [
    'slate' => '#64748b',
    'gray' => '#6b7280',
    'zinc' => '#71717a',
    'neutral' => '#737373',
    'stone' => '#78716c',
    'red' => '#ef4444',
    'orange' => '#f97316',
    'amber' => '#f59e0b',
    'yellow' => '#eab308',
    'lime' => '#84cc16',
    'green' => '#22c55e',
    'emerald' => '#10b981',
    'teal' => '#14b8a6',
    'cyan' => '#06b6d4',
    'sky' => '#0ea5e9',
    'blue' => '#3b82f6',
    'indigo' => '#6366f1',
    'violet' => '#8b5cf6',
    'purple' => '#a855f7',
    'fuchsia' => '#d946ef',
    'pink' => '#ec4899',
    'rose' => '#f43f5e',
];

$colorOptions = [];
foreach ($colors as $value => $name) {
    $colorOptions[] = [
        'value' => $value,
        'name' => $name,
        'hex' => $colorHexValues[$value] ?? '#6b7280',
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
