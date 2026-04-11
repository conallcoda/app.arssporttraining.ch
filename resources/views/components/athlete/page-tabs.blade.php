@props(['active'])

<div class="athlete-toolbar sticky top-0 z-20 border-b border-zinc-200 bg-zinc-100/95 px-0 py-0 backdrop-blur dark:border-zinc-700 dark:bg-zinc-800/95 sm:static sm:border-0 sm:bg-transparent sm:px-0 sm:py-0 sm:backdrop-blur-none">
    <div class="sm:mx-auto sm:max-w-2xl sm:px-4">
        <div class="flex flex-col gap-0 sm:gap-2">
            <x-athlete.page-tabs.row>
                <flux:tabs variant="segmented" class="w-full [&>[data-flux-tabs]]:grid [&>[data-flux-tabs]]:w-full [&>[data-flux-tabs]]:grid-cols-2">
                    <flux:tab
                        name="record"
                        icon="dumbbell"
                        href="{{ route('athlete.dashboard') }}"
                        wire:navigate
                        :selected="$active === 'record'"
                    >
                        Train
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
            </x-athlete.page-tabs.row>

            @if (trim((string) $slot) !== '')
                {{ $slot }}
            @endif
        </div>
    </div>
</div>
