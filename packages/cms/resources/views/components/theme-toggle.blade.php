<flux:button
    x-data
    variant="subtle"
    square
    aria-label="Toggle color scheme"
    x-on:click="$flux.appearance = $flux.dark ? 'light' : 'dark'"
>
    <flux:icon.sun x-show="$flux.dark" variant="mini" class="text-zinc-500 dark:text-white" />
    <flux:icon.moon x-show="! $flux.dark" variant="mini" class="text-zinc-500 dark:text-white" />
</flux:button>
