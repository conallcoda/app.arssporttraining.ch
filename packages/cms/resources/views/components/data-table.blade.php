@props([
    'columns' => [],
    'empty' => false,
    'emptyText' => 'No rows found.',
    'emptyColspan' => null,
    'tableClass' => 'table-fixed',
])

@php
    $resolvedColumns = collect($columns)
        ->map(function ($column): array {
            if (is_string($column)) {
                return ['label' => $column];
            }

            return is_array($column) ? $column : [];
        })
        ->values()
        ->all();
    $resolvedEmptyColspan = $emptyColspan ?? max(1, count($resolvedColumns));
@endphp

<flux:table class="{{ $tableClass }}">
    <flux:table.columns>
        @foreach ($resolvedColumns as $column)
            @php
                $label = (string) ($column['label'] ?? '');
                $align = (string) ($column['align'] ?? 'start');
                $class = trim((string) ($column['class'] ?? ''));
                $helpText = $column['help_text'] ?? null;
                $helpTitle = $column['help_title'] ?? null;
                $sticky = (bool) ($column['sticky'] ?? false);
            @endphp

            <flux:table.column
                :align="$align !== 'start' ? $align : null"
                :sticky="$sticky"
                :help-text="$helpText"
                :help-title="$helpTitle"
                :class="$class !== '' ? $class : null"
            >
                {{ $label }}
            </flux:table.column>
        @endforeach
    </flux:table.columns>

    <flux:table.rows>
        @if ($empty)
            <flux:table.row>
                <flux:table.cell :colspan="$resolvedEmptyColspan" class="py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $emptyText }}
                </flux:table.cell>
            </flux:table.row>
        @else
            {{ $slot }}
        @endif
    </flux:table.rows>
</flux:table>
