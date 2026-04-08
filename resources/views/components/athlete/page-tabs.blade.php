@props(['active'])

<flux:tabs variant="segmented" class="mb-6 max-sm:!hidden w-full [&>[data-flux-tabs]]:grid [&>[data-flux-tabs]]:w-full [&>[data-flux-tabs]]:grid-cols-2">
    <flux:tab
        name="record"
        icon="clipboard-list"
        href="{{ route('athlete.dashboard') }}"
        wire:navigate
        :selected="$active === 'record'"
    >
        Record
    </flux:tab>
    <flux:tab
        name="calendar"
        icon="calendar"
        href="{{ route('athlete.dashboard.calendar') }}"
        wire:navigate
        :selected="$active === 'calendar'"
    >
        Calendar
    </flux:tab>
</flux:tabs>
