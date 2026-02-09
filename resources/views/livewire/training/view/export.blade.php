<div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
    <div
        class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex items-center justify-between">
        <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Export Training Plans</h3>
    </div>

    <div class="p-6">
        @if ($this->users->count() > 0 && $this->programs->count() > 0)
            <div class="mb-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="sm">Select Athletes to Export</flux:heading>
                    <div class="flex gap-2">
                        <flux:button variant="ghost" size="sm" wire:click="selectAll">
                            Select All
                        </flux:button>
                        <flux:button variant="ghost" size="sm" wire:click="deselectAll">
                            Deselect All
                        </flux:button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($this->users as $userItem)
                        @php
                            $isExportable = in_array($userItem->id, $this->exportableUserIds);
                        @endphp
                        <div wire:key="export-user-{{ $userItem->id }}">
                            <flux:checkbox
                                wire:model.live="selectedUserIds"
                                value="{{ $userItem->id }}"
                                label="{{ $userItem->name }}"
                                :disabled="!$isExportable"
                            />
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mb-6 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center gap-3 mb-4">
                    <flux:heading size="sm">Preview:</flux:heading>
                    <flux:select wire:model.live="previewUserId" size="sm" class="w-auto" placeholder="Select athlete to preview">
                        @foreach ($this->users as $userOption)
                            <flux:select.option value="{{ $userOption->id }}">
                                {{ $userOption->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                @if ($this->previewUser && count($this->previewPlans) > 0)
                    <div class="space-y-6">
                        @foreach ($this->previewPlans as $plan)
                            <div>
                                <flux:heading size="xs" class="mb-2">{{ $plan->getTitle() }}</flux:heading>
                                <div class="overflow-x-auto border border-zinc-300 dark:border-zinc-600 rounded">
                                    <table class="text-sm border-collapse">
                                        <tbody>
                                            @foreach ($plan->getRows() as $row)
                                                @if ($plan->isExerciseHeaderRow($row))
                                                    <tr>
                                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle font-bold w-10"
                                                            rowspan="{{ $row['exerciseRowCount'] ?? 1 }}">
                                                            {{ $row['exerciseNumber'] }}
                                                        </td>
                                                        <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center align-middle font-bold w-60"
                                                            rowspan="{{ $row['exerciseRowCount'] ?? 1 }}">
                                                            {{ $row['exerciseName'] }}
                                                        </td>
                                                        @foreach ($row['headerCells'] as $index => $cell)
                                                            @if ($index > 1)
                                                                <td
                                                                    class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center whitespace-nowrap font-semibold bg-zinc-200 dark:bg-zinc-700">
                                                                    {{ $cell }}
                                                                </td>
                                                            @endif
                                                        @endforeach
                                                    </tr>
                                                @else
                                                    <tr class="{{ $plan->getTailwindRowClass($row) }}">
                                                        <td
                                                            class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center whitespace-nowrap font-medium">
                                                            {{ $row['label'] ?? '' }}
                                                        </td>
                                                        @foreach ($row['cells'] as $cell)
                                                            <td
                                                                class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center whitespace-nowrap">
                                                                {{ $cell }}
                                                            </td>
                                                        @endforeach
                                                        <td
                                                            class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center whitespace-nowrap">
                                                            {{ $row['tempo'] ?? '' }}
                                                        </td>
                                                        <td
                                                            class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center whitespace-nowrap">
                                                            {{ $row['rest'] ?? '' }}
                                                        </td>
                                                        <td
                                                            class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center whitespace-nowrap">
                                                            {{ $row['date1'] ?? '' }}
                                                        </td>
                                                        <td
                                                            class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center whitespace-nowrap">
                                                            {{ $row['date2'] ?? '' }}
                                                        </td>
                                                        <td
                                                            class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center whitespace-nowrap font-medium">
                                                            {{ $row['week'] ?? '' }}
                                                        </td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @elseif ($this->previewUser)
                    <div class="text-center py-4">
                        <p class="text-zinc-500 dark:text-zinc-400">
                            No training data available for {{ $this->previewUser->name }}. Please configure their
                            measured values in the Plan tab.
                        </p>
                    </div>
                @else
                    <div class="text-center py-4">
                        <p class="text-zinc-500 dark:text-zinc-400">
                            Select an athlete above to preview their training plan.
                        </p>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ count($selectedUserIds) }} athlete(s) selected
                </p>
                <flux:button variant="primary" wire:click="export" wire:loading.attr="disabled"
                    :disabled="count($selectedUserIds) === 0">
                    <span wire:loading.remove wire:target="export">Export</span>
                    <span wire:loading wire:target="export">Exporting...</span>
                </flux:button>
            </div>
        @else
            <div class="text-center py-8">
                <p class="text-zinc-500 dark:text-zinc-400">
                    @if ($this->users->count() === 0)
                        No athletes assigned to this training plan. Please add athletes in the Setup tab.
                    @elseif ($this->programs->count() === 0)
                        No programs configured. Please add programs and exercises in the Programs tab.
                    @endif
                </p>
            </div>
        @endif
    </div>
</div>
