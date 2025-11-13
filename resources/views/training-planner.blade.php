<div>
    @if ($season)
        <div class="space-y-6">
            {{-- Season Header --}}
            <div class="mb-8">
                <flux:heading size="xl" class="mb-2">{{ $season->name }}</flux:heading>
                <flux:subheading>
                    {{ $blocks->count() }} blocks •
                    {{ $blocks->sum(fn($block) => $block->children->count()) }} weeks total
                </flux:subheading>
            </div>

            {{-- Training Blocks Grid --}}
            <div class="grid gap-6">
                @foreach ($blocks as $blockIndex => $block)
                    <flux:card class="p-0">
                        {{-- Block Header --}}
                        <div class="px-6 py-4 bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                            <div class="flex items-center justify-between">
                                <div>
                                    <flux:heading size="lg">Block {{ $block->sequence + 1 }}</flux:heading>
                                    <flux:subheading class="mt-1">
                                        {{ $block->children->count() }} weeks
                                    </flux:subheading>
                                </div>
                                <div class="flex gap-2">
                                    <flux:button size="sm" variant="ghost" icon="pencil">Edit</flux:button>
                                    <flux:button size="sm" variant="ghost" icon="trash">Delete</flux:button>
                                </div>
                            </div>
                        </div>

                        {{-- Week Tabs --}}
                        <div class="border-b border-zinc-200 dark:border-zinc-700 px-6">
                            <flux:tabs wire:model="activeWeeks.{{ $blockIndex }}">
                                @foreach ($block->children as $weekIndex => $week)
                                    <flux:tab name="{{ $weekIndex }}">Week {{ $week->sequence + 1 }}</flux:tab>
                                @endforeach
                            </flux:tabs>
                        </div>

                        {{-- Active Week Content --}}
                        <div class="p-6">
                            @php
                                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                $timePeriods = ['Morning', 'Afternoon', 'Evening'];
                                $activeWeek = $block->children[$activeWeeks[$blockIndex]] ?? $block->children->first();
                            @endphp

                            @if ($activeWeek)
                                <div class="space-y-4">
                                    {{-- Week Title --}}
                                    <div class="flex items-center justify-between">
                                        <flux:heading size="md">Week {{ $activeWeek->sequence + 1 }}</flux:heading>
                                        <div class="flex gap-2">
                                            <flux:button size="xs" variant="ghost" icon="pencil">Edit</flux:button>
                                            <flux:button size="xs" variant="ghost" icon="trash">Delete</flux:button>
                                        </div>
                                    </div>

                                    {{-- Week Grid --}}
                                    <div class="overflow-x-auto">
                                        <table class="w-full border-collapse">
                                            <thead>
                                                <tr>
                                                    <th class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-2 text-left text-sm font-semibold w-24">
                                                        Time
                                                    </th>
                                                    @foreach ($days as $day)
                                                        <th class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-2 text-center text-sm font-semibold">
                                                            {{ $day }}
                                                        </th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($timePeriods as $period)
                                                    <tr>
                                                        <td class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-2 text-sm font-medium">
                                                            {{ $period }}
                                                        </td>
                                                        @foreach ($days as $day)
                                                            <td class="border border-zinc-200 dark:border-zinc-700 p-2 h-24 align-top hover:bg-zinc-50 dark:hover:bg-zinc-800/50 cursor-pointer transition-colors">
                                                                {{-- Session content will go here --}}
                                                                <div class="h-full flex items-center justify-center text-zinc-400 dark:text-zinc-600">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                                    </svg>
                                                                </div>
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </flux:card>
                @endforeach

                {{-- Add Block Card --}}
                <flux:card
                    class="hover:shadow-md transition-shadow cursor-pointer border-2 border-dashed border-zinc-300 dark:border-zinc-600">
                    <div class="flex flex-col items-center justify-center text-center py-12">
                        <svg class="w-12 h-12 text-zinc-400 mb-3" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <flux:heading size="lg" class="text-zinc-500 mb-1">Add Block</flux:heading>
                        <flux:subheading class="text-zinc-400">Create a new training block</flux:subheading>
                    </div>
                </flux:card>
            </div>
        </div>
    @else
        {{-- Empty State --}}
        <div class="flex flex-col items-center justify-center py-24">
            <svg class="w-24 h-24 text-zinc-300 dark:text-zinc-600 mb-6" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <flux:heading size="xl" class="mb-2">No Training Plan Found</flux:heading>
            <flux:subheading class="mb-6">Create your first training season to get started</flux:subheading>
            <flux:button variant="primary" icon="plus">Create Season</flux:button>
        </div>
    @endif
</div>
