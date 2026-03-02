<x-section title="Groups" class="w-64 shrink-0 sticky top-4 self-start max-h-[calc(100vh-6rem)] overflow-y-auto">
    <div class="flex flex-col gap-1">
        @foreach ($this->groups as $group)
            <div x-data="{ open: false }" wire:key="group-{{ $group->id }}">
                <div class="flex items-center">
                    <button
                        wire:click="selectGroup({{ $group->id }})"
                        class="flex-1 rounded-lg px-3 py-2 text-sm font-medium text-left transition-colors {{ $this->isGroupSelected($group->id) ? 'bg-zinc-800 text-white dark:bg-white dark:text-zinc-800' : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700' }}"
                    >
                        {{ $group->name }}
                    </button>
                    @if ($navigateEvent)
                        <button
                            wire:click="navigate('group', {{ $group->id }})"
                            class="shrink-0 rounded p-1.5 text-zinc-400 hover:text-zinc-600 hover:bg-zinc-100 dark:hover:text-zinc-300 dark:hover:bg-zinc-700 transition-colors"
                        >
                            <flux:icon.square-arrow-out-up-right class="size-3.5" />
                        </button>
                    @endif
                    <button
                        @click="open = !open"
                        class="shrink-0 p-1.5 text-zinc-400 transition-colors"
                    >
                        <flux:icon.chevron-down
                            class="size-4 transition-transform duration-200"
                            ::class="open && 'rotate-180'"
                        />
                    </button>
                </div>
                <div x-show="open" x-collapse>
                        <div class="flex flex-col gap-0.5 pl-4 pt-1">
                            @foreach ($group->members as $member)
                                <div class="flex items-center gap-1" wire:key="member-{{ $group->id }}-{{ $member->id }}">
                                    @if ($mode === 'single-athlete' || $mode === 'multi-athlete')
                                        <button
                                            wire:click="selectUser({{ $group->id }}, {{ $member->id }})"
                                            class="flex-1 rounded-lg px-3 py-1.5 text-sm text-left transition-colors {{ $this->isSelected($group->id, $member->id) ? 'bg-zinc-800 text-white dark:bg-white dark:text-zinc-800' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700/50' }}"
                                        >
                                            {{ $member->name }}
                                        </button>
                                    @else
                                        <div class="flex-1 rounded px-3 py-1.5 text-sm text-zinc-600 dark:text-zinc-400 cursor-default">
                                            {{ $member->name }}
                                        </div>
                                    @endif
                                    @if ($navigateEvent)
                                        <button
                                            wire:click="navigate('user', {{ $group->id }}, {{ $member->id }})"
                                            class="shrink-0 rounded p-1 text-zinc-400 hover:text-zinc-600 hover:bg-zinc-100 dark:hover:text-zinc-300 dark:hover:bg-zinc-700 transition-colors"
                                        >
                                            <flux:icon.square-arrow-out-up-right class="size-3" />
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
            </div>
        @endforeach
    </div>
</x-section>
