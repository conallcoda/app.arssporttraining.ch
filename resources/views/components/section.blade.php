@props(['title', 'text' => null])

<fieldset
    {{ $attributes->merge(['class' => 'border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 space-y-4 [&>legend+*]:!mt-0']) }}>
    <legend class="mb-0 px-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
        {{ $title }}</legend>
    @if ($text)
        <flux:text class="!mt-0">{{ $text }}</flux:text>
    @endif
    {{ $slot }}
</fieldset>
