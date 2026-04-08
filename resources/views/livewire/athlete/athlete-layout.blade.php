<div>
    <nav class="fixed inset-x-0 bottom-0 z-10 border-t border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 sm:hidden">
        <div class="mx-auto flex max-w-2xl items-end justify-around px-4 pb-[env(safe-area-inset-bottom)]">
            <a
                href="{{ route('athlete.dashboard') }}"
                wire:navigate
                class="flex flex-col items-center gap-1 pb-2 pt-3 {{ request()->routeIs('athlete.dashboard') ? 'text-zinc-900 dark:text-white' : 'text-zinc-400 dark:text-zinc-500' }}"
            >
                <flux:icon.clipboard-list class="size-5" />
                <span class="text-[10px] font-medium">Record</span>
            </a>

            <button
                type="button"
                wire:click="$dispatch('open-readiness-modal')"
                class="-mt-9 flex flex-col items-center"
            >
                <div class="rounded-full border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <x-athlete.readiness-gauge :score="$readinessScore" :color="$readinessColor" :size="64" :show-label="false" />
                </div>
            </button>

            <a
                href="{{ route('athlete.dashboard.calendar') }}"
                wire:navigate
                class="flex flex-col items-center gap-1 pb-2 pt-3 {{ request()->routeIs('athlete.dashboard.calendar', 'athlete.dashboard.calendar.week') ? 'text-zinc-900 dark:text-white' : 'text-zinc-400 dark:text-zinc-500' }}"
            >
                <flux:icon.calendar class="size-5" />
                <span class="text-[10px] font-medium">Calendar</span>
            </a>
        </div>
    </nav>

    <flux:modal name="readiness-check" class="md:w-96">
        <livewire:athlete.readiness-check :key="'readiness-check-' . $readinessModalOpened" />
    </flux:modal>
</div>
