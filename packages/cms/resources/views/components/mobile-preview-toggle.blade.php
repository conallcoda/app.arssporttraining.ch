<flux:button
    variant="subtle"
    square
    aria-label="Mobile preview"
    :href="route('cms.mobile-preview', ['url' => request()->url()])"
>
    <flux:icon.smartphone variant="mini" class="text-zinc-500 dark:text-white" />
</flux:button>
