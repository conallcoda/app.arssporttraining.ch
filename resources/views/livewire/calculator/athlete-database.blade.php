<div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
    <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex items-center justify-between">
        <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Simplified Athlete Database</h3>
        <x-help-tooltip content="Contains athlete test data including reps, weight, and calculated 1RM. Click on reps or weight values to edit them inline." />
    </div>
    <div class="p-4 overflow-x-auto">
        <table class="w-full border-collapse border border-zinc-300 dark:border-zinc-600">
            <thead>
                <tr class="bg-zinc-100 dark:bg-zinc-800">
                    <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">ID</th>
                    <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Name</th>
                    <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Test (Reps)</th>
                    <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Test (Weight)</th>
                    <th class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">Test (1RM)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($athletes as $ath)
                    <tr wire:key="athlete-{{ $ath->id }}">
                        <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">
                            {{ $ath->id }}
                        </td>
                        <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">
                            {{ $ath->name }}
                        </td>
                        @foreach ($ath->tests as $testIndex => $test)
                            <td class="border border-zinc-300 dark:border-zinc-600 w-20 h-10 p-0"
                                x-data="editable_cell($wire, 'updateAthleteTestReps', [{{ $ath->id }}, {{ $testIndex }}], {{ $test->reps }})" @click="startEditing">
                                <div x-show="!editing" class="px-3 py-2 cursor-pointer text-center"
                                    x-text="value"></div>
                                <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                    @blur="save" @keydown="handleKeydown" type="number"
                                    step="1" min="1"
                                    class="w-full h-full text-center border border-black outline-none focus:border-black focus:ring-0" />
                            </td>
                            <td class="border border-zinc-300 dark:border-zinc-600 w-24 h-10 p-0"
                                x-data="editable_cell($wire, 'updateAthleteTestWeight', [{{ $ath->id }}, {{ $testIndex }}], {{ $test->weight }}, ' kg')" @click="startEditing">
                                <div x-show="!editing" class="px-3 py-2 cursor-pointer text-center"
                                    x-text="value + ' kg'"></div>
                                <input x-show="editing" x-cloak x-ref="input" x-model="value"
                                    @blur="save" @keydown="handleKeydown" type="number"
                                    step="0.5" min="1"
                                    class="w-full h-full text-center border border-black outline-none focus:border-black focus:ring-0" />
                            </td>
                            <td class="border border-zinc-300 px-3 py-2 dark:border-zinc-600">
                                {{ number_format($test->oneRepMax, 1) }} kg
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
