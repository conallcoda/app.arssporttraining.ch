@props([
    'label',
    'value',
])

<div class="rounded-lg px-4 py-2 bg-zinc-50 dark:bg-zinc-800">
    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</div>
    <div class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $value }}</div>
</div>
